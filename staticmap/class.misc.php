<?php

# From http://www.php.net/manual/en/features.commandline.php#83843
function arguments($args){
  array_shift($args);
  $endofoptions = false;

  $ret = array(
    	'commands' => array()
   	,	'options' => array()
    ,	'flags' => array()
    ,	'arguments' => array()
	);

  while ( $arg = array_shift($args) ) {
    // if we have reached end of options we cast all remaining argvs as arguments
    if ($endofoptions) {
      $ret['arguments'][] = $arg;
      continue;
    }

    // Is it a command? (prefixed with --)
    if ( substr( $arg, 0, 2 ) === '--' ) {
      // is it the end of options flag?
      if (!isset ($arg[3])) {
        $endofoptions = true; // end of options;
        continue;
      }

      $value = "";
      $com   = substr( $arg, 2 );

      // is it the syntax '--option=argument'?
      if (strpos($com,'='))
        list($com,$value) = @split("=",$com,2);

      // is the option not followed by another option but by arguments
      elseif (strpos($args[0],'-') !== 0) {
        while (strpos($args[0],'-') !== 0)
          $value .= array_shift($args).' ';
        $value = rtrim($value,' ');
      }

      $ret['options'][$com] = !empty($value) ? $value : true;
      continue;
    }

    // Is it a flag or a serial of flags? (prefixed with -)
    if ( substr( $arg, 0, 1 ) === '-' ){
      for ($i = 1; isset($arg[$i]) ; $i++)
        $ret['flags'][] = $arg[$i];
      continue;
    }

    // finally, it is not option, nor flag, nor argument
    $ret['commands'][] = $arg;
    continue;
  }

  if (!count($ret['options']) && !count($ret['flags'])) {
    $ret['arguments'] = array_merge($ret['commands'], $ret['arguments']);
    $ret['commands'] = array();
  }
	return $ret;
}

?>