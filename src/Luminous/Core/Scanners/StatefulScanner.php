<?php

/** @cond CORE */

namespace Luminous\Core\Scanners;

use Exception;
use Luminous\Core\Utils;

/**
 *
 * @brief Experimental transition table driven scanner
 *
 * The stateful scanner follows a transition table and generates a hierarchical
 * token tree. As such, the states follow a hierarchical parent->child
 * relationship rather than a strict from->to
 *
 * A node in the token tree looks like this:
 *
 * @code array('token_name' => 'name','children' => array(...)) @endcode
 *
 * Children is an ordered list and its elements may be either other token
 * nodes or just strings. We override tagged to try to collapse this into XML
 * while still applying filters.
 *
 *
 * We now store patterns as the following tuple:
 * @code ($name, $open_pattern, $teminate_pattern). @endcode
 * The termination pattern may be null, in which case the $open_pattern
 * is complete. No transitions can occur within a complete state because
 * the patterns' match is fixed.
 *
 * We have two stacks. One is LuminousStatefulScanner::$token_tree_stack,
 * which stores the token tree, and the other is a standard state stack which
 * stores the current state data. State data is currently a pattern, as the
 * above tuple.
 *
 * @warning Currently 'stream filters' are not applied, because we at no point
 * end up with a flat stream of tokens. Although the rule name remapper is
 * applied.
 */
class StatefulScanner extends SimpleScanner
{
    /**
     * @brief Transition table
     */
    protected $transitions = array();

    /**
     * @brief Legal transitions for the current state
     *
     * @see LuminousStatefulScanner::load_transitions()
     */
    protected $legalTransitions = array();

    /**
     * @brief Pattern list
     *
     * Pattern array. Each pattern is a tuple of
     * @code ($name, $open_pattern, $teminate_pattern) @endcode
     */
    protected $patterns = array();

    /**
     * @brief The token tree
     *
     * The tokens we end up with are a tree which we build as we go along. The
     * easiest way to build it is to keep track of the currently active node on
     * top of a stack. When the node is completed, we pop it and insert it as
     * a child of the element which is now at the top of the stack.
     *
     * At the end of the process we end up with one element in here which is
     * the root node.
     */
    protected $tokenTreeStack = array();

    /**
     * Records whether or not the FSM has been set up for the first time.
     * @see setup()
     */
    private $setup = false;
    /**
     * remembers the state on the last iteration so we know whether or not
     * to load in a new transition-set
     */
    private $lastState = null;

    /**
     * Cache of transition rules
     * @see next_start_data()
     */
    private $transitionRuleCache = array();

    /**
     * Pushes a new token onto the stack as a child of the currently active
     * token
     *
     * @see push_state
     * @internal
     */
    public function pushChild($child)
    {
        assert(!empty($this->tokenTreeStack));
        $this->tokenTreeStack[] = $child;
    }

    /**
     * @brief Pushes a state
     *
     * @param $state_data A tuple of ($name, $open_pattern, $teminate_pattern).
     * This should be as it is stored in LuminousStatefulScanner::patterns
     *
     * This actually causes two push operations. One is onto the token_tree_stack,
     * and the other is onto the actual stack. The former creates a new token,
     * the latter is used for state information
     */
    public function pushState($stateData)
    {
        $tokenNode = array('token_name' => $stateData[0], 'children' => array());
        $this->pushChild($tokenNode);
        $this->push($stateData);
    }

    /**
     * @brief Pops a state from the stack.
     *
     * The top token on the token_tree_stack is popped and appended as a child to
     * the new top token.
     *
     * The top state on the state stack is popped and discarded.
     * @throw Exception if there is only the initial state on the stack
     * (we cannot pop the initial state, because then we have no state at all)
     */
    public function popState()
    {
        $c = count($this->tokenTreeStack);
        if ($c <= 1) {
            throw new Exception('Attempted to pop the initial state');
        }
        $s = array_pop($this->tokenTreeStack);
        // -2 because we popped once since counting
        $this->tokenTreeStack[$c - 2]['children'][] = $s;
        $this->pop();
    }

    /**
     * @brief Adds a state transition
     *
     * This is a helper function for LuminousStatefulScanner::transitions, you
     * can specify it directly instead
     * @param $from The parent state
     * @param $to The child state
     */
    public function addTransition($from, $to)
    {
        if (!isset($this->transitions[$from])) {
            $this->transitions[$from] = array();
        }
        $this->transitions[$from][] = $to;
    }

    /**
     * @brief Gets the name of the current state
     *
     * @returns The name of the current state
     */
    public function stateName()
    {
        $stateData = $this->state();
        if ($stateData === null) {
            return 'initial';
        }
        $stateName = $stateData[0];
        return $stateName;
    }

    /**
     * @brief Adds a pattern
     *
     * @param $name the name of the pattern/state
     * @param $pattern Either the entire pattern, or just its opening delimiter
     * @param $end If $pattern was just the opening delimiter, $end is the closing
     * delimiter. Separating the two delimiters like this makes the state flexible
     * length, as state transitions can occur inside it.
     * @param $consume Not currently observed. Might never be. Don't specify this yet.
     */
    public function addPattern($name, $pattern, $end = null, $consume = true)
    {
        $this->patterns[] = array($name, $pattern, $end, $consume);
    }

    /**
     * @brief Loads legal state transitions for the current state
     *
     * Loads in legal state transitions into the legal_transitions array
     * according to the current state
     */
    public function loadTransitions()
    {
        $stateName = $this->stateName();
        if ($this->lastState === $stateName) {
            return;
        }
        $this->lastState = $stateName;
        if (isset($this->transitions[$stateName])) {
            $this->legalTransitions = $this->transitions[$this->stateName()];
        } else {
            $this->legalTransitions = array();
        }
    }

    /**
     * @brief Looks for the next state-pop sequence (close/end) for the current state
     *
     * @returns Data in the same format as get_next: a tuple of (next, matches).
     * If no match is found, next is -1 and matches is null
     */
    public function nextEndData()
    {
        $stateData = $this->state();
        if ($stateData === null) {
            return array(-1, null); // init/root state
        }
        $termPattern = $stateData[2];
        assert($termPattern !== null);
        $data = $this->getNext(array($termPattern));
        return $data;
    }

    /**
     * @brief Looks for the next legal state transition
     *
     * @returns A tuple of (pattern_data, next, matches).
     * If no match is found, next is -1 and pattern_data and matches is null
     */
    public function nextStartData()
    {
        $patterns = array();
        $states = array();
        $sn = $this->stateName();
        // at the moment we are using get_next_named, so we have to convert
        // our patterns into key=>pattern so it can return to us a key. We use
        // numerical indices which also correspond with 'states' for full pattern
        // data. We are caching this.
        // TODO turns out get_next_named is pretty slow and we'd be better off
        // caching some results inside the pattern data
        if (isset($this->transitionRuleCache[$sn])) {
            list($patterns, $states) = $this->transitionRuleCache[$sn];
        } else {
            foreach ($this->legalTransitions as $t) {
                foreach ($this->patterns as $p) {
                    if ($p[0] === $t) {
                        $patterns[] = $p[1];
                        $states[] = $p;
                    }
                }
            }
            $this->transitionRuleCache[$sn] = array($patterns, $states);
        }
        $next = $this->getNextNamed($patterns);
        // map to real state data
        if ($next[1] !== -1) {
            $next[0] = $states[$next[0]];
        }
        return $next;
    }

    /**
     * @brief Sets up the FSM
     *
     * If the caller has omitted to specify an initial state then one is created,
     * with valid transitions to all other known states. We also push the
     * initial state onto the tree stack, and add a type mapping from the initial
     * type to @c NULL.
     */
    protected function setup()
    {
        if ($this->setup) {
            return;
        }
        $this->setup = true;
        if (!isset($this->transitions['initial'])) {
            $initial = array();
            foreach ($this->patterns as $p) {
                $initial[] = $p[0];
            }
            $this->transitions['initial'] = $initial;
        }
        $this->tokenTreeStack[] = array('token_name' => 'initial', 'children' => array());
        $this->ruleTagMap['initial'] = null;
    }

    /**
     * Records a string as a child of the currently active token
     * @warning the second and third parameters are not applicable to this
     * method, they are only present to suppress PHP warnings. If you set them,
     * an exception is thrown.
     */
    public function record($str, $dummy1 = null, $dummy2 = null)
    {
        if ($dummy1 !== null || $dummy2 !== null) {
            throw new Exception(
                'Luminous\\Core\\Scanners\\StatefulScanner::record does not currently observe its second and third '
                . 'parameters'
            );
        }
        // NOTE to self: if ever this needs to change, don't call count on $c.
        // Dereference it first: http://bugs.php.net/bug.php?id=34540
        $c = &$this->tokenTreeStack[count($this->tokenTreeStack) - 1]['children'];
        $c[] = $str;
    }

    /**
     * @brief Records a complete token
     * This is shorthand for pushing a new node onto the stack, recording its
     * text, and then popping it
     *
     * @param $str the string
     * @param $type the token type
     */
    public function recordToken($str, $type)
    {
        $stateData = array($type);
        $this->pushState($stateData);
        $this->record($str);
        $this->popState();
    }

    /**
     * @brief Helper function to record a range of the string
     * @param $from the start index
     * @param $to the end index
     * @param $type dummy argument
     * This is shorthand for
     * <code> $this->record(substr($this->string(), $from, $to-$from)</code>
     *
     * @throw RangeException if the range is invalid (i.e. $to < $from)
     *
     * An empty range (i.e. $to === $from) is allowed, but it is essentially a
     * no-op.
     */
    public function recordRange($from, $to, $type = null)
    {
        if ($type !== null) {
            throw new Exception(
                'type argument not supported in Luminous\\Core\\Scanners\\StatefulScanner::recordRange'
            );
        }
        if ($to === $from) {
            return;
        } elseif ($to > $from) {
            $this->record(substr($this->string(), $from, $to - $from), $type);
        } else {
            throw new RangeException("Invalid range supplied [$from, $to]");
        }
    }

    /**
     * Generic main function which observes the transition table
     */
    public function main()
    {
        $this->setup();
        while (!$this->eos()) {
            $p = $this->pos();
            $state = $this->stateName();

            $this->loadTransitions();
            list($nextPatternData, $nextPatternIndex, $nextPatternMatches) = $this->nextStartData();
            list($endIndex, $endMatches) = $this->nextEndData();

            if (($nextPatternIndex <= $endIndex || $endIndex === -1) && $nextPatternIndex !== -1) {
                // we're pushing a new state
                if ($p < $nextPatternIndex) {
                    $this->recordRange($p, $nextPatternIndex);
                }
                $newPos = $nextPatternIndex;
                $this->pos($newPos);
                $tok = $nextPatternData[0];
                if (isset($this->overrides[$tok])) {
                    // call override
                    $ret = call_user_func($this->overrides[$tok], $nextPatternMatches);
                    if ($ret === true) {
                        break;
                    }
                    if ($this->stateName() === $state && $this->pos() <= $newPos) {
                        throw new Exception('Override failed to either advance the pointer or change the state');
                    }
                } else {
                    // no override
                    $this->posShift(strlen($nextPatternMatches[0]));
                    $this->pushState($nextPatternData);
                    $this->record($nextPatternMatches[0]);
                    if ($nextPatternData[2] === null) {
                        // state was a full pattern, so pop now
                        $this->popState();
                    }
                }
            } elseif ($endIndex !== -1) {
                // we're at the end of a state, record what's left and pop it
                $to = $endIndex + strlen($endMatches[0]);
                $this->recordRange($this->pos(), $to);
                $this->pos($to);
                $this->popState();
            } else {
                // no more matches, consume the rest of the stirng and break
                $this->record($this->rest());
                $this->terminate();
                break;
            }
            if ($this->stateName() === $state && $this->pos() <= $p) {
                throw new Exception('Failed to advance pointer in state' . $this->stateName());
            }
        }

        // unterminated states will have left some tokens open, we need to
        // close these so there's just the root node on the stack
        assert(count($this->tokenTreeStack) >= 1);
        while (count($this->tokenTreeStack) > 1) {
            $this->popState();
        }

        return $this->tokenTreeStack[0];
    }

    /**
     * Recursive function to collapse the token tree into XML
     * @internal
     */
    protected function collapseTokenTree($node)
    {
        $text = '';
        foreach ($node['children'] as $c) {
            if (is_string($c)) {
                $text .= Utils::escapeString($c);
            } else {
                $text .= $this->collapseTokenTree($c);
            }
        }
        $tokenName = $node['token_name'];
        $token = array($node['token_name'], $text, true);

        $token_ = $this->ruleMapperFilter(array($token));
        $token = $token_[0];

        if (isset($this->filters[$tokenName])) {
            foreach ($this->filters[$tokenName] as $filter) {
                $token = call_user_func($filter[1], $token);
            }
        }
        list($tokenName, $text,) = $token;
        return ($tokenName === null) ? $text : Utils::tagBlock($tokenName, $text);
    }

    public function tagged()
    {
        $stream = $this->collapseTokenTree($this->tokenTreeStack[0]);
        return $stream;
    }
}

/** @endcond CORE */
