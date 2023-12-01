<?php

namespace Verseles\Flyclone\Test\Unit;

trait Helpers
{
  public function random_string($length = 7)
  : string
  {
	 return substr(md5(mt_rand()), 0, $length);
  }
}
