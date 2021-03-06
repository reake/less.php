<?php

class Less_Tree_Paren extends Less_Tree{

	public $value;
	public $type = 'Paren';

	public function __construct($value) {
		$this->value = $value;
	}

	function accept($visitor){
		$this->value = $visitor->visitObj($this->value);
	}

	function genCSS( $env, $output ){
		$output->add( '(' );
		$this->value->genCSS( $env, $output );
		$output->add( ')' );
	}

	public function compile($env) {
		return new Less_Tree_Paren($this->value->compile($env));
	}

}
