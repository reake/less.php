<?php

//less.js : lib/less/functions.js

namespace Less;

class Environment
{
	/**
	 * @var array
	 */
	public $frames;

	/**
	 * @var bool
	 */
	public $compress;

	/**
	 * @var bool
	 */
	public $debug;

	/**
	 * @var bool
	 */
	public $strictImports;

	/**
	 * @var array
	 */
	public $mediaBlocks = array();

	/**
	 * @var array
	 */
	public $mediaPath = array();

	/**
	 * @var array
	 */
	public $paths = array();

	public $selectors = array();

	public $rootpath = '';

	public $charset;

	public function __construct()
	{
		$this->frames = array();
		$this->compress = false;
		$this->debug = false;
		$this->strictImports =  false;
	}

	/**
	 * @return bool
	 */
	public function getCompress()
	{
		return $this->compress;
	}

	/**
	 * @param bool $compress
	 * @return void
	 */
	public function setCompress($compress)
	{
		$this->compress = $compress;
	}

	/**
	 * @return bool
	 */
	public function getDebug()
	{
		return $this->debug;
	}

	/**
	 * @param $debug
	 * @return void
	 */
	public function setDebug($debug)
	{
		$this->debug = $debug;
	}

	public function unshiftFrame($frame)
	{
		array_unshift($this->frames, $frame);
	}

	public function shiftFrame()
	{
		return array_shift($this->frames);
	}

	public function addFrame($frame)
	{
		$this->frames[] = $frame;
	}

	public function addFrames(array $frames)
	{
		$this->frames = array_merge($this->frames, $frames);
	}

	static public function operate ($op, $a, $b)
	{
		switch ($op) {
			case '+': return $a + $b;
			case '-': return $a - $b;
			case '*': return $a * $b;
			case '/': return $a / $b;
		}
	}

	static public function find($obj, $fun){
		foreach($obj as $i => $o) {

			if ($r = call_user_func($fun, $o)) {

				return $r;
			}
		}
		return null;
	}

	static public function clamp($val)
	{
		return min(1, max(0, $val));
	}

	static public function number($n){

		if ($n instanceof \Less\Node\Dimension) {
			return floatval( $n->unit->is('%') ? $n->value / 100 : $n->value);
		} else if (is_numeric($n)) {
			return $n;
		} else {
			throw new \Less\Exception\CompilerException("color functions take numbers as parameters");
		}
	}

	static public function scaled($n, $size) {
		if( $n instanceof \Less\Node\Dimension && $n->unit->is('%') ){
			return (float)$n->value * $size / 100;
		} else {
			return \Less\Environment::number($n);
		}
	}

	public function rgb ($r, $g, $b)
	{
		return $this->rgba($r, $g, $b, 1.0);
	}

	public function rgba($r, $g, $b, $a)
	{
		$rgb = array_map(function ($c) { return \Less\Environment::scaled($c,256); }, array($r, $g, $b));
		$a = self::number($a);
		return new \Less\Node\Color($rgb, $a);
	}

	public function hsl($h, $s, $l){
		return $this->hsla($h, $s, $l, 1.0);
	}

	public function hsla($h, $s, $l, $a){

		$h = fmod(self::number($h), 360) / 360; // Classic % operator will change float to int
		$s = self::number($s);
		$l = self::number($l);
		$a = self::number($a);

		$m2 = $l <= 0.5 ? $l * ($s + 1) : $l + $s - $l * $s;

		$m1 = $l * 2 - $m2;

		$hue = function ($h) use ($m1, $m2) {
			$h = $h < 0 ? $h + 1 : ($h > 1 ? $h - 1 : $h);
			if	  ($h * 6 < 1) return $m1 + ($m2 - $m1) * $h * 6;
			else if ($h * 2 < 1) return $m2;
			else if ($h * 3 < 2) return $m1 + ($m2 - $m1) * (2/3 - $h) * 6;
			else				 return $m1;
		};


		return $this->rgba($hue($h + 1/3) * 255,
						   $hue($h)	   * 255,
						   $hue($h - 1/3) * 255,
						   $a);
	}

	function hsv($h, $s, $v) {
		return $this->hsva($h, $s, $v, 1.0);
	}

	function hsva($h, $s, $v, $a) {
		$h = ((\Less\Environment::number($h) % 360) / 360 ) * 360;
		$s = \Less\Environment::number($s);
		$v = \Less\Environment::number($v);
		$a = \Less\Environment::number($a);

		$i = floor(($h / 60) % 6);
		$f = ($h / 60) - $i;

		$vs = array( $v,
				  $v * (1 - $s),
				  $v * (1 - $f * $s),
				  $v * (1 - (1 - $f) * $s));

		$perm = array(array(0, 3, 1),
					array(2, 0, 1),
					array(1, 0, 3),
					array(1, 2, 0),
					array(3, 1, 0),
					array(0, 1, 2));

		return $this->rgba($vs[$perm[$i][0]] * 255,
						 $vs[$perm[$i][1]] * 255,
						 $vs[$perm[$i][2]] * 255,
						 $a);
	}

	public function hue($color)
	{
		$c = $color->toHSL();
		return new \Less\Node\Dimension(round($c['h']));
	}

	public function saturation($color)
	{
		$c = $color->toHSL();
		return new \Less\Node\Dimension(round($c['s'] * 100), '%');
	}

	public function lightness($color)
	{
		$c = $color->toHSL();
		return new \Less\Node\Dimension(round($c['l'] * 100), '%');
	}

	public function red($color) {
		return new \Less\Node\Dimension( $color->rgb[0] );
	}

	public function green($color) {
		return new \Less\Node\Dimension( $color->rgb[1] );
	}

	public function blue($color) {
		return new \Less\Node\Dimension( $color->rgb[2] );
	}

	public function alpha($color){
		$c = $color->toHSL();
		return new \Less\Node\Dimension($c['a']);
	}

	function luma ($color) {
		return new \Less\Node\Dimension(round(
			(0.2126 * ($color->rgb[0]/255) +
			0.7152 * ($color->rgb[1]/255) +
			0.0722 * ($color->rgb[2]/255))
			* $color->alpha * 100), '%');
	}

	public function saturate($color, $amount)
	{
		$hsl = $color->toHSL();

		$hsl['s'] += $amount->value / 100;
		$hsl['s'] = self::clamp($hsl['s']);

		return $this->hsla($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
	}

	public function desaturate($color, $amount)
	{
		$hsl = $color->toHSL();

		$hsl['s'] -= $amount->value / 100;
		$hsl['s'] = self::clamp($hsl['s']);

		return $this->hsla($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
	}

	public function lighten($color, $amount)
	{
		$hsl = $color->toHSL();

		$hsl['l'] += $amount->value / 100;
		$hsl['l'] = self::clamp($hsl['l']);

		return $this->hsla($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
	}

	public function darken($color, $amount)
	{

		if( $color instanceof \Less\Node\Color ){
			$hsl = $color->toHSL();

			$hsl['l'] -= $amount->value / 100;
			$hsl['l'] = self::clamp($hsl['l']);

			return $this->hsla($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
		}

		$this->Expected('color',$color);
	}

	public function fadein($color, $amount)
	{
		$hsl = $color->toHSL();

		if ($amount->unit == '%') {
			$hsl['a'] += $amount->value / 100;
		} else {
			$hsl['a'] += $amount->value;
		}
		$hsl['a'] = self::clamp($hsl['a']);

		return $this->hsla($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
	}

	public function fadeout($color, $amount)
	{
		$hsl = $color->toHSL();

		if ($amount->unit == '%') {
			$hsl['a'] -= $amount->value / 100;
		} else {
			$hsl['a'] -= $amount->value;
		}
		$hsl['a'] = self::clamp($hsl['a']);

		return $this->hsla($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
	}

	public function fade($color, $amount)
	{
		$hsl = $color->toHSL();

		if ($amount->unit == '%') {
			$hsl['a'] = $amount->value / 100;
		} else {
			$hsl['a'] = $amount->value;
		}
		$hsl['a'] = self::clamp($hsl['a']);

		return $this->hsla($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
	}

	public function spin($color, $amount)
	{
		$hsl = $color->toHSL();
		$hue = fmod($hsl['h'] + $amount->value, 360);

		$hsl['h'] = $hue < 0 ? 360 + $hue : $hue;

		return $this->hsla($hsl['h'], $hsl['s'], $hsl['l'], $hsl['a']);
	}

	//
	// Copyright (c) 2006-2009 Hampton Catlin, Nathan Weizenbaum, and Chris Eppstein
	// http://sass-lang.com
	//
	public function mix($color1, $color2, $weight = null)
	{
		if (!$weight) {
			$weight = new \Less\Node\Dimension('50', '%');
		}

		$p = $weight->value / 100.0;
		$w = $p * 2 - 1;
		$hsl1 = $color1->toHSL();
		$hsl2 = $color2->toHSL();
		$a = $hsl1['a'] - $hsl2['a'];

		$w1 = (((($w * $a) == -1) ? $w : ($w + $a) / (1 + $w * $a)) + 1) / 2;
		$w2 = 1 - $w1;

		$rgb = array($color1->rgb[0] * $w1 + $color2->rgb[0] * $w2,
					 $color1->rgb[1] * $w1 + $color2->rgb[1] * $w2,
					 $color1->rgb[2] * $w1 + $color2->rgb[2] * $w2);

		$alpha = $color1->alpha * $p + $color2->alpha * (1 - $p);

		return new \Less\Node\Color($rgb, $alpha);
	}

	public function greyscale($color)
	{
		return $this->desaturate($color, new \Less\Node\Dimension(100));
	}

	function contrast( $color, $dark = false, $light = false, $threshold = false) {
        // filter: contrast(3.2);
        // should be kept as is, so check for color
		if( !property_exists($color,'rgb') ){
            return null;
        }
        if( $light === false ){
			$light = $this->rgba(255, 255, 255, 1.0);
		}
		if( $dark === false ){
			$dark = $this->rgba(0, 0, 0, 1.0);
		}
		if( $threshold === false ){
			$threshold = 0.43;
		} else {
			$threshold = \Less\Environment::number($threshold);
		}

		if (((0.2126 * ($color->rgb[0]/255) + 0.7152 * ($color->rgb[1]/255) + 0.0722 * ($color->rgb[2]/255)) * $color->alpha) < $threshold) {
			return $light;
		} else {
			return $dark;
		}
	}

	public function e ($str)
	{
		return new \Less\Node\Anonymous($str instanceof \Less\Node\JavaScript ? $str->evaluated : $str);
	}

	public function escape ($str){
		return new \Less\Node\Anonymous(urlencode($str->value));
	}

	public function _percent(){
		$numargs = func_num_args();
		$quoted = func_get_arg(0);

		$args = func_get_args();
		array_shift($args);
		$str = $quoted->value;

		foreach($args as $arg) {
			$str = preg_replace_callback('/%[sda]/i', function($token) use ($arg) {
				$token = $token[0];
				$value = stristr($token, 's') ? $arg->value : $arg->toCSS();
				return preg_match('/[A-Z]$/', $token) ? urlencode($value) : $value;
			}, $str, 1);
		}
		$str = str_replace('%%', '%', $str);

		return new \Less\Node\Quoted('"' . $str . '"', $str);
	}

    function unit($val, $unit = null ){
        return new \Less\Node\Dimension($val->value, $unit ? $unit->toCSS() : "");
    }

	public function convert($val, $unit){
		return $val->convertTo($unit->value);
	}

	public function round($n, $f = false) {

		$fraction = 0;
		if( $f !== false ){
			$fraction = $f->value;
		}

		return $this->_math('round',null, $n, $fraction);
	}

	public function pi(){
		return new \Less\Node\Dimension(M_PI);
	}

	public function mod($a, $b) {
		return new \Less\Node\Dimension( $a->value % $b->value, $a->unit);
	}

    function pow($x, $y) {
		if( is_numeric($x) && is_numeric($y) ){
			$x = new \Less\Node\Dimension($x);
			$y = new \Less\Node\Dimension($y);
		}elseif( !($x instanceof \Less\Node\Dimension) || !($y instanceof \Less\Node\Dimension) ){
			throw new \Less\Exception\CompilerException('Arguments must be numbers');
		}

		return new \Less\Node\Dimension( pow($x->value, $y->value), $x->unit );
    }

	// var mathFunctions = [{name:"ce ...
	public function ceil( $n ){		return $this->_math('ceil', null, $n); }
	public function floor( $n ){	return $this->_math('floor', null, $n); }
	public function sqrt( $n ){		return $this->_math('sqrt', null, $n); }
	public function abs( $n ){		return $this->_math('abs', null, $n); }

	public function tan( $n ){		return $this->_math('tan', '', $n);	}
	public function sin( $n ){		return $this->_math('sin', '', $n);	}
	public function cos( $n ){		return $this->_math('cos', '', $n);	}

	public function atan( $n ){		return $this->_math('atan', 'rad', $n);	}
	public function asin( $n ){		return $this->_math('asin', 'rad', $n);	}
	public function acos( $n ){		return $this->_math('acos', 'rad', $n);	}

	private function _math() {
		$args = func_get_args();
		$fn = array_shift($args);
		$unit = array_shift($args);

		if ($args[0] instanceof \Less\Node\Dimension) {

			if( $unit === null ){
				$unit = $args[0]->unit;
			}else{
				$args[0] = $args[0]->unify();
			}
			$args[0] = (float)$args[0]->value;
			return new \Less\Node\Dimension( call_user_func_array($fn, $args), $unit);
		} else if (is_numeric($args[0])) {
			return call_user_func_array($fn,$args);
		} else {
			throw new \Less\Exception\CompilerException("math functions take numbers as parameters");
		}
	}

	public function argb($color) {
		return new \Less\Node\Anonymous($color->toARGB());
	}

	public function percentage($n) {
		return new \Less\Node\Dimension($n->value * 100, '%');
	}

	public function color($n) {
		if ($n instanceof \Less\Node\Quoted) {
			return new \Less\Node\Color(substr($n->value, 1));
		} else {
			throw new \Less\Exception\CompilerException("Argument must be a string");
		}
	}

	public function iscolor($n) {
		return $this->_isa($n, 'Less\Node\Color');
	}

	public function isnumber($n) {
		return $this->_isa($n, 'Less\Node\Dimension');
	}

	public function isstring($n) {
		return $this->_isa($n, 'Less\Node\Quoted');
	}

	public function iskeyword($n) {
		return $this->_isa($n, 'Less\Node\Keyword');
	}

	public function isurl($n) {
		return $this->_isa($n, 'Less\Node\Url');
	}

	public function ispixel($n) {
		return $n instanceof \Less\Node\Dimension && $n->unit->is('px')
			? new \Less\Node\Keyword('true') : new \Less\Node\Keyword('false');
	}

	public function ispercentage($n) {
		return $n instanceof \Less\Node\Dimension && $n->unit->is('%')
			? new \Less\Node\Keyword('true') : new \Less\Node\Keyword('false');
	}

	public function isem($n) {
		return $n instanceof \Less\Node\Dimension && $n->unit->is('em')
			? new \Less\Node\Keyword('true') : new \Less\Node\Keyword('false');
	}

	private function _isa($n, $type) {
		return is_a($n, $type) ? new \Less\Node\Keyword('true') : new \Less\Node\Keyword('false');
	}

	/* Blending modes */

	function multiply($color1, $color2) {
		$r = $color1->rgb[0] * $color2->rgb[0] / 255;
		$g = $color1->rgb[1] * $color2->rgb[1] / 255;
		$b = $color1->rgb[2] * $color2->rgb[2] / 255;
		return $this->rgb($r, $g, $b);
	}
	function screen($color1, $color2) {
		$r = 255 - (255 - $color1->rgb[0]) * (255 - $color2->rgb[0]) / 255;
		$g = 255 - (255 - $color1->rgb[1]) * (255 - $color2->rgb[1]) / 255;
		$b = 255 - (255 - $color1->rgb[2]) * (255 - $color2->rgb[2]) / 255;
		return $this->rgb($r, $g, $b);
	}
	function overlay($color1, $color2) {
		$r = $color1->rgb[0] < 128 ? 2 * $color1->rgb[0] * $color2->rgb[0] / 255 : 255 - 2 * (255 - $color1->rgb[0]) * (255 - $color2->rgb[0]) / 255;
		$g = $color1->rgb[1] < 128 ? 2 * $color1->rgb[1] * $color2->rgb[1] / 255 : 255 - 2 * (255 - $color1->rgb[1]) * (255 - $color2->rgb[1]) / 255;
		$b = $color1->rgb[2] < 128 ? 2 * $color1->rgb[2] * $color2->rgb[2] / 255 : 255 - 2 * (255 - $color1->rgb[2]) * (255 - $color2->rgb[2]) / 255;
		return $this->rgb($r, $g, $b);
	}
	function softlight($color1, $color2) {
		$t = $color2->rgb[0] * $color1->rgb[0] / 255;
		$r = $t + $color1->rgb[0] * (255 - (255 - $color1->rgb[0]) * (255 - $color2->rgb[0]) / 255 - $t) / 255;
		$t = $color2->rgb[1] * $color1->rgb[1] / 255;
		$g = $t + $color1->rgb[1] * (255 - (255 - $color1->rgb[1]) * (255 - $color2->rgb[1]) / 255 - $t) / 255;
		$t = $color2->rgb[2] * $color1->rgb[2] / 255;
		$b = $t + $color1->rgb[2] * (255 - (255 - $color1->rgb[2]) * (255 - $color2->rgb[2]) / 255 - $t) / 255;
		return $this->rgb($r, $g, $b);
	}
	function hardlight($color1, $color2) {
		$r = $color2->rgb[0] < 128 ? 2 * $color2->rgb[0] * $color1->rgb[0] / 255 : 255 - 2 * (255 - $color2->rgb[0]) * (255 - $color1->rgb[0]) / 255;
		$g = $color2->rgb[1] < 128 ? 2 * $color2->rgb[1] * $color1->rgb[1] / 255 : 255 - 2 * (255 - $color2->rgb[1]) * (255 - $color1->rgb[1]) / 255;
		$b = $color2->rgb[2] < 128 ? 2 * $color2->rgb[2] * $color1->rgb[2] / 255 : 255 - 2 * (255 - $color2->rgb[2]) * (255 - $color1->rgb[2]) / 255;
		return $this->rgb($r, $g, $b);
	}
	function difference($color1, $color2) {
		$r = abs($color1->rgb[0] - $color2->rgb[0]);
		$g = abs($color1->rgb[1] - $color2->rgb[1]);
		$b = abs($color1->rgb[2] - $color2->rgb[2]);
		return $this->rgb($r, $g, $b);
	}
	function exclusion($color1, $color2) {
		$r = $color1->rgb[0] + $color2->rgb[0] * (255 - $color1->rgb[0] - $color1->rgb[0]) / 255;
		$g = $color1->rgb[1] + $color2->rgb[1] * (255 - $color1->rgb[1] - $color1->rgb[1]) / 255;
		$b = $color1->rgb[2] + $color2->rgb[2] * (255 - $color1->rgb[2] - $color1->rgb[2]) / 255;
		return $this->rgb($r, $g, $b);
	}
	function average($color1, $color2) {
		$r = ($color1->rgb[0] + $color2->rgb[0]) / 2;
		$g = ($color1->rgb[1] + $color2->rgb[1]) / 2;
		$b = ($color1->rgb[2] + $color2->rgb[2]) / 2;
		return $this->rgb($r, $g, $b);
	}
	function negation($color1, $color2) {
		$r = 255 - abs(255 - $color2->rgb[0] - $color1->rgb[0]);
		$g = 255 - abs(255 - $color2->rgb[1] - $color1->rgb[1]);
		$b = 255 - abs(255 - $color2->rgb[2] - $color1->rgb[2]);
		return $this->rgb($r, $g, $b);
	}
	function tint($color, $amount) {
		return $this->mix( $this->rgb(255,255,255), $color, $amount);
	}

	function shade($color, $amount) {
		return $this->mix($this->rgb(0, 0, 0), $color, $amount);
	}

	function extract($values, $index ) {
		$index = $index->value - 1; // (1-based index)
		return $values->value[$index];
	}

	function datauri($mimetype, $path = null ) {

		if( $path ){
			$path = $path->value;
		}
		$mimetype = $mimetype->value;
		$useBase64 = false;

		// detect the mimetype if not given
		if( !$path ){
			$path = $mimetype;

			/*
			$mime = require('mime');
			mimetype = mime.lookup(path);

			// use base 64 unless it's an ASCII or UTF-8 format
			var charset = mime.charsets.lookup(mimetype);
			useBase64 = ['US-ASCII', 'UTF-8'].indexOf(charset) < 0;
			if (useBase64) mimetype += ';base64';
			*/

		}else{
			$useBase64 = preg_match('/;base64$/',$mimetype);
		}

		$buf = @file_get_contents($path);
		if( $buf ){
			$buf = $useBase64 ? base64_encode($buf) : rawurlencode($buf);
			$path = "'data:"+$mimetype+','+$buf+"'";
		}

		return new \Less\Node\Url( new \Less\Node\Anonymous($path) );
	}


	private function Expected( $type, $arg ){

		$debug = debug_backtrace();
		array_shift($debug);
		$last = array_shift($debug);
		$last = array_intersect_key($last,array('function'=>'','class'=>'','line'=>''));

		$message = 'Object of type '.get_class($arg).' passed to darken function. Expecting `Color`. '.$arg->toCSS().'. '.print_r($last,true);
		throw new \Less\Exception\CompilerException($message);

	}




}
