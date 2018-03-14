<?php

namespace JaneOlszewska\Itinerant;

use JaneOlszewska\Itinerant\Action\ValidateTraversalStrategy;
use JaneOlszewska\Itinerant\Action\ValidateUserRegisteredTraversalStrategy;
use JaneOlszewska\Itinerant\ChildHandler\ChildHandlerInterface;
use JaneOlszewska\Itinerant\ChildHandler\RestOfElements;

/**
 * @todo Documentation ¯\_(ツ)_/¯
 */
class TraversalStrategy
{
    const ID = 'id';
    const FAIL = 'fail';
    const SEQ = 'seq';
    const CHOICE = 'choice';
    const ALL = 'all';
    const ONE = 'one';
    const ADHOC = 'adhoc';

    // intermediate stage, required due to passing of results
    private const CHOICE_INTERMEDIATE = 'choice-intermediate';
    private const SEQ_INTERMEDIATE = 'seq-intermediate';
    private const ALL_INTERMEDIATE = 'all-intermediate';
    private const ONE_INTERMEDIATE = 'one-intermediate';
    private const NOP = 'nop';

    private const INBUILT_STRATEGIES = [
        self::ID,
        self::FAIL,
        self::SEQ,
        self::CHOICE,
        self::ALL,
        self::ONE,
        self::ADHOC,
        self::CHOICE_INTERMEDIATE,
        self::SEQ_INTERMEDIATE,
        self::ALL_INTERMEDIATE,
        self::ONE_INTERMEDIATE,
        self::NOP
    ];

    /** @var ValidateTraversalStrategy */
    private $validatePreApplicationAction;

    /** @var ValidateUserRegisteredTraversalStrategy */
    private $validatePreRegistrationAction;

    /** @var ChildHandlerInterface */
    private $childHandler;

    /** @var array */
    private $strategies = [
        // Note that values for predefined strategies currently not in use
        self::ID => [self::class, 'id'],
        self::FAIL => [self::class, 'fail'],
        self::SEQ => [self::class, 'seq'],
        self::CHOICE => [self::class, 'choice'],
        self::ALL => [self::class, 'all'],
        self::ONE => [self::class, 'one'],
        self::ADHOC => [self::class, 'adhoc'],
    ];

    /** @var int[] */
    private $argCounts = [
        // Note that this array does not include non-public predefined strategies, which are therefore disallowed
        // by validation and made usable only internally
        self::ID => 0,
        self::FAIL => 0,
        self::SEQ => 2,
        self::CHOICE => 2,
        self::ALL => 1,
        self::ONE => 1,
        self::ADHOC => 2,
    ];

    /** @var StrategyStack */
    private $stack;

    /** @var mixed */
    private $fail;

    /**
     * TraversalStrategy constructor.
     * @param ChildHandlerInterface $childHandler
     * @param mixed $fail A representation of "fail", valid for whatever nodes/adhoc methods will be used
     */
    public function __construct(ChildHandlerInterface $childHandler, $fail)
    {
        $this->stack = new StrategyStack();
        $this->childHandler = $childHandler;
        $this->fail = $fail;
    }

    /**
     * @return ChildHandlerInterface
     */
    public function getChildHandler(): ChildHandlerInterface
    {
        return $this->childHandler;
    }

    /**
     * @return mixed
     */
    public function getFail()
    {
        return $this->fail;
    }

    /** @var TraversalStrategy */
    private $ts;

    /**
     * @return TraversalStrategy
     */
    private function getInternalValidationTS()
    {
        if (!isset($this->ts)) {
            $this->ts = new TraversalStrategy(new RestOfElements(), false);
        }

        return $this->ts;
    }

    /**
     * Witness the ultimate coolness: TraversalStrategy is self-validating!
     *
     * @param mixed $strategy
     * @throws \InvalidArgumentException if $strategy is found invalid
     */
    private function validateBeforeApplication($strategy): void
    {
        $validate = 'validate';

        if (($ts = $this->getInternalValidationTS()) && !isset($ts->strategies[$validate])) {
            $this->validatePreApplicationAction = new ValidateTraversalStrategy($this->ts->childHandler);
            $a = $this->validatePreApplicationAction;
            $isNotCallableArray = function ($d) {
                return (is_array($d) && is_callable($d)) ? null : $d;
            };
            // register without validation to avoid infinite recursion
            $this->ts->registerWithoutValidation(
                $validate,
                ['choice',
                    ['seq',
                        ['adhoc', 'fail', $isNotCallableArray], // if doesn't fail, safe to nest further
                        ['seq', ['adhoc', 'fail', $a], ['all', [$validate]]], // top-down application of $a
                    ],
                    ['adhoc', 'fail', $a] // just validate without nesting
                ],
                0
            );
        }

        $this->validatePreApplicationAction->setStrategyArgumentCounts($this->argCounts);

        // apply without validation to avoid infinite recursion
        $result = $this->ts->applyWithoutValidation($validate, $strategy);

        if ($result === $this->ts->fail) {
            $error = $this->validatePreApplicationAction->getLastError();
            throw new \InvalidArgumentException("Invalid argument structure for the strategy: {$error}");
        }
    }

    /**
     * Needs slightly expanded validation rules for registered strategies: they contain argument substitution markers.
     *
     * @param string $strategyKey
     * @param array $strategy
     * @param int $argCount
     */
    private function validateBeforeRegistering(string $strategyKey, array $strategy, int $argCount): void
    {
        if (in_array($strategyKey, self::INBUILT_STRATEGIES)) {
            throw new \InvalidArgumentException("Cannot overwrite inbuilt strategy key: {$strategyKey}");
        }

        $validate = 'validate_registered';

        if (($ts = $this->getInternalValidationTS()) && !isset($ts->strategies[$validate])) {
            $this->validatePreRegistrationAction = new ValidateUserRegisteredTraversalStrategy($this->ts->childHandler);
            $a = $this->validatePreRegistrationAction;
            // register without validation to avoid infinite recursion
            $this->ts->registerWithoutValidation(
                $validate,
                ['seq', ['adhoc', 'fail', $a], ['all', [$validate]]], // top-down application of $a
                0
            );
        }

        $this->validatePreRegistrationAction->setStrategyArgumentCounts(
            array_merge([$strategyKey => $argCount], $this->argCounts)
        );

        // apply without validation to avoid infinite recursion
        $result = $this->ts->applyWithoutValidation($validate, $strategy);

        if ($result === $this->ts->fail) {
            $error = $this->validatePreRegistrationAction->getLastError();
            throw new \InvalidArgumentException("Invalid argument structure for the strategy: {$error}");
        }
    }

    /**
     * @param string $key
     * @param array $expansion
     * @param int $argCount
     */
    public function registerStrategy(string $key, array $expansion, int $argCount): void
    {
        $this->validateBeforeRegistering($key, $expansion, $argCount);
        $this->registerWithoutValidation($key, $expansion, $argCount);
    }

    private function registerWithoutValidation(string $key, array $expansion, int $argCount): void
    {
        $this->strategies[$key] = $expansion;
        $this->argCounts[$key] = $argCount;
    }

    /**
     * @param array|string $s
     * @param mixed $datum
     * @return mixed
     */
    public function apply($s, $datum)
    {
        $this->validateBeforeApplication($s);
        return $this->applyWithoutValidation($s, $datum);
    }

    /**
     * @param array|string $s
     * @param mixed $datum
     * @return mixed
     */
    private function applyWithoutValidation($s, $datum)
    {
        $this->push($s, $datum);
        $currentDatum = $datum;

        do {
            $key = $this->stack->getCurrentStratKey();
            $args = $this->stack->getCurrentStratArguments();

            // todo: so this could be simplified. Later. I'm in the dirty hacking mode.

            switch ($key) {
                case self::FAIL:
                    $result = $this->fail($currentDatum);
                    break;
                case self::ID:
                    $result = $this->id($currentDatum);
                    break;
                case self::SEQ:
                    $result = $this->seq($currentDatum, ...$args);
                    break;
                case self::SEQ_INTERMEDIATE:
                    $result = $this->seqIntermediate($currentDatum, ...$args);
                    break;
                case self::CHOICE:
                    $result = $this->choice($currentDatum, ...$args);
                    break;
                case self::CHOICE_INTERMEDIATE:
                    $result = $this->choiceIntermediate($currentDatum);
                    break;
                case self::ALL:
                    $result = $this->all($currentDatum, ...$args);
                    break;
                case self::ALL_INTERMEDIATE:
                    $result = $this->allIntermediate($currentDatum, ...$args);
                    break;
                case self::ONE:
                    $result = $this->one($currentDatum, ...$args);
                    break;
                case self::ONE_INTERMEDIATE:
                    $result = $this->oneIntermediate($currentDatum, ...$args);
                    break;
                case self::ADHOC:
                    $result = $this->adhoc($currentDatum, ...$args);
                    break;
                case self::NOP:
                    /*
                     * Including this just in case there is some logic error in the library.
                     *
                     * In actual use NOP is only pushed as a filler, to be immediately popped in the last lines of
                     * the do...while loop when it is discovered $result is not null - see all/one implementations.
                     */
                    throw new \DomainException('Logic error: NOP should never be evaluated');
                    break;
                default:
                    $result = $this->userDefined($currentDatum, ...$args);
            }

            if ($result === null) {
                // strategy non-terminal, continue applying it to the same datum
                continue;
            }

            // problem: when allowing result=null to indicate non-terminal strategy,
            // how to achieve the passing/preservation of result?

            // strategy terminal: $currentDatum transformed into $result
            // pass the result into the strategy lower on the stack
            $this->stack->pop();
            $currentDatum = $result;
        } while (!$this->stack->isEmpty());

        return $result;
    }

    private function id($previousResult)
    {
        return $previousResult;
    }

    private function fail($previousResult)
    {
        return $this->fail;
    }

    private function seq($previousResult, $s1, $s2)
    {
        $this->stack->pop();

        $this->push([self::SEQ_INTERMEDIATE, $s2], $this->fail);
        $this->push($s1, $previousResult);

        return null; // always non-terminal
    }

    private function seqIntermediate($previousResult, $s2)
    {
        $res = $previousResult;

        if ($this->fail !== $previousResult) {
            $this->stack->pop();
            $this->push($s2, $previousResult);
            $res = null;
        }

        return $res;
    }

    private function choice($previousResult, $s1, $s2)
    {
        $this->stack->pop(); // remove self

        $this->push($s2, $this->fail);
        $this->push([self::CHOICE_INTERMEDIATE], $previousResult);
        $this->push($s1, $previousResult);

        return null; // always non-terminal
    }

    private function choiceIntermediate($previousResult)
    {
        $res = $this->stack->getOriginalDatum();

        if ($this->fail !== $previousResult) {
            $this->stack->pop(); // pop self; $s2 will be auto-popped
            $res = $previousResult; // pass $s1 result
        }

        return $res;
    }

    private function all($previousResult, $s1)
    {
        // if $d has no children: return $d, strategy terminal independent of what $s1 actually is
        $res = $previousResult;
        $unprocessed = $this->childHandler->getChildren($previousResult);

        if ($unprocessed) {
            $this->stack->pop();

            $this->push([self::ALL_INTERMEDIATE, $s1], $previousResult, $unprocessed, []);
            $this->push($s1, $unprocessed[0]);
            $this->push([self::NOP]); // only here to be immediately popped
            $res = $unprocessed[0];
        }

        return $res;
    }

    private function allIntermediate($previousResult, $s1)
    {
        $res = $previousResult;

        if ($this->fail !== $previousResult) {
            $originalResult = $this->stack->getOriginalDatum();

            // if the result of the last child resolution wasn't fail, continue
            $unprocessed = $this->stack->getUnprocessedChildren();
            array_shift($unprocessed);

            $processed = $this->stack->getProcessedChildren();
            $processed[] = $previousResult;

            if ($unprocessed) { // there's more to process
                $this->stack->pop();

                $this->push([self::ALL_INTERMEDIATE, $s1], $originalResult, $unprocessed, $processed);
                $this->push($s1, $unprocessed[0]);
                $this->push([self::NOP]); // only here to be popped
                $res = $unprocessed[0];
            } else {
                $this->childHandler->setChildren($originalResult, $processed);
                $res = $originalResult;
            }
        }

        return $res;
    }

    private function one($previousResult, $s1)
    {
        // if $d has no children: fail, strategy terminal independent of what $s1 actually is
        $res = $this->fail;
        $unprocessed = $this->childHandler->getChildren($previousResult);

        if ($unprocessed) {
            $this->stack->pop();

            // not interested in previously processed results: thus null
            $this->push([self::ONE_INTERMEDIATE, $s1], null, $unprocessed, null);
            $this->push($s1, $unprocessed[0]);
            $this->push([self::NOP]); // only here to be popped
            $res = $unprocessed[0];
        }

        return $res;
    }

    private function oneIntermediate($previousResult, $s1)
    {
        $res = $previousResult;

        if ($this->fail === $previousResult) {
            // if the result of the last child resolution was fail, need to try with the next one (if exists)

            $unprocessed = $this->stack->getUnprocessedChildren();
            array_shift($unprocessed);

            if ($unprocessed) { // fail, but there's more to process
                $this->stack->pop();

                // not interested in previously processed results: thus null
                $this->push([self::ONE_INTERMEDIATE, $s1], null, $unprocessed, null);
                $this->push($s1, $unprocessed[0]);
                $this->push([self::NOP]); // only here to be popped
                $res = $unprocessed[0];
            }

            // else: well we processed everything and nothing succeeded so: FAIL ($res === $previousResult === FAIL)
        }

        return $res;
    }

    private function adhoc($previousResult, $s, $a)
    {
        $applied = false;
        $res = null; // non-terminal by default

        if (is_callable($a)) {
            // strategy resolved to applied action; terminal unless null returned
            // todo: document this clearly somewhere
            $res = $a($previousResult);
            $applied = ($res !== null);
        }

        if (!$applied) {
            $this->stack->pop(); // remove self, fully resolved
            $this->push($s, $previousResult); // resolve strategy $s with $d
        }

        return $res;
    }

    private function userDefined($previousResult, ...$args)
    {
        $key = $this->stack->getCurrentStratKey();

        $originalDatum = $this->stack->getOriginalDatum();

        $strategy = $this->strategies[$key];

        // substitute numeric placeholders with the actual arguments
        // @TODO: yep, it's ugly, and it doesn't validate the index
        array_walk_recursive($strategy, function (&$value) use ($args) {
            if (is_numeric($value)) {
                $value = $args[(int) $value];
            }
        });

        $this->stack->pop();
        $this->push($strategy, $originalDatum);

        return null; // always non-terminal
    }

    private function push($strategy, $datum = null, ?array $unprocessed = null, ?array $processed = null): void
    {
        $strategy = $this->sanitiseStrategy($strategy);
        $this->stack->push($strategy, $datum, $unprocessed, $processed);
    }

    private function sanitiseStrategy($s)
    {
        if (is_string($s)
            && ($s == self::FAIL || $s == self::ID || ($this->argCounts[$s] == 0))
        ) {
            $s = [$s];
        }

        return $s;
    }
}