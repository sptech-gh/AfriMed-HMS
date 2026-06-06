<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class OrderStateMachine {

	protected $CI;
	protected $transitions;
	protected $states;
	protected $aliases;

	public function __construct() {
		$this->CI =& get_instance();
		$this->transitions = $this->CI->config->item('order_state_transitions');
		$this->states = $this->CI->config->item('order_states');
		$this->aliases = $this->CI->config->item('order_state_aliases');
		if (!is_array($this->transitions)) { $this->transitions = array(); }
		if (!is_array($this->states)) { $this->states = array(); }
		if (!is_array($this->aliases)) { $this->aliases = array(); }
	}

	public function normalize($state) {
		$st = strtoupper(trim((string)$state));
		if ($st === '') return '';
		if (isset($this->aliases[$st])) {
			return strtoupper(trim((string)$this->aliases[$st]));
		}
		return $st;
	}

	public function is_canonical_state($state) {
		$st = $this->normalize($state);
		if ($st === '') return false;
		return in_array($st, $this->states, true);
	}

	public function can_transition($current, $next) {
		$cur = $this->normalize($current);
		$nxt = $this->normalize($next);

		if ($cur === '' || $nxt === '') return false;
		if ($cur === $nxt) return true;

		// Only enforce when both states are part of the canonical state machine
		if (!$this->is_canonical_state($cur) || !$this->is_canonical_state($nxt)) {
			return true;
		}

		if (!isset($this->transitions[$cur]) || !is_array($this->transitions[$cur])) return false;
		return in_array($nxt, $this->transitions[$cur], true);
	}

	public function assert_transition($current, $next) {
		if (!$this->can_transition($current, $next)) {
			$cur = $this->normalize($current);
			$nxt = $this->normalize($next);
			log_message('error', 'Invalid state transition: ' . $cur . ' -> ' . $nxt);
			return false;
		}
		return true;
	}
}
