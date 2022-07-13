<?php


namespace CloudAtlas\Flyclone\Test\Unit;


use CloudAtlas\Flyclone\Providers\Provider;
use CloudAtlas\Flyclone\Rclone;
use PHPUnit\Framework\Exception;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

abstract class AbstractTwoProvidersTest extends TestCase
{
  use Helpers;

  protected string $leftProviderName        = 'undefined_disk';
  protected string $rightProviderName       = 'undefined_disk2';
  protected string $left_working_directory  = '/tmp';
  protected string $right_working_directory = '/tmp';

  final public function setLeftProviderName ( string $leftProviderName ): void
  {
	 $this->leftProviderName = $leftProviderName;
  }

  final public function getLeftProviderName (): string
  {
	 return $this->leftProviderName;
  }

  final public function getRightProviderName (): string
  {
	 return $this->rightProviderName;
  }

  final public function setRightProviderName ( string $rightProviderName ): void
  {
	 $this->rightProviderName = $rightProviderName;
  }

  final public function getLeftWorkingDirectory (): string
  {
	 return $this->left_working_directory;
  }

  final public function setLeftWorkingDirectory ( string $left_working_directory ): void
  {
	 $this->left_working_directory = $left_working_directory;
  }

  final public function getRightWorkingDirectory (): string
  {
	 return $this->right_working_directory;
  }

  final public function setRightWorkingDirectory ( string $right_working_directory ): void
  {
	 $this->right_working_directory = $right_working_directory;
  }


  abstract public function instantiate_left_provider (): Provider;

  abstract public function instantiate_right_provider (): Provider;

  /**
	* @test
	* @depends      instantiate_left_provider
	* @depends      instantiate_right_provider
	* @noinspection PhpUnitTestsInspection
	* @throws ExpectationFailedException|InvalidArgumentException|Exception
	*/
  public function instantiate_with_two_providers ( $left_side, $right_side ): Rclone
  {
	 $two_sides = new Rclone($left_side, $right_side);

	 self::assertInstanceOf(Rclone::class, $two_sides);

	 return $two_sides;
  }

  /**
	* @test
	* @depends instantiate_with_two_providers
	*
	* @param Rclone $two_sides
	*
	* @return array
	* @throws ExpectationFailedException|InvalidArgumentException
	*/
  public function touch_a_file_on_left_side ( Rclone $two_sides ): array
  {
	 $temp_filepath = $this->getLeftWorkingDirectory() . '/flyclone_' . $this->random_string();

	 $result = $two_sides->touch($temp_filepath);

	 self::assertTrue($result);

	 $file = $two_sides->is_file($temp_filepath);

	 self::assertTrue($file->exists, 'File not created');

	 self::assertEquals(0, $file->details->Size ?? 9999, 'File should be empty by now');

	 return [ $two_sides, $temp_filepath ];
  }

  /**
	* @test
	* @depends touch_a_file_on_left_side
	* @throws ExpectationFailedException|InvalidArgumentException
	*/
  public function write_to_a_file_on_left_side ( $params ): array
  {
	 $content = 'But my father lives at https://helio.me :)';

	 /** @var Rclone $two_sides */
	 [ $two_sides, $temp_filepath ] = $params;

	 $two_sides->rcat($temp_filepath, $content);

	 $file_content = $two_sides->cat($temp_filepath);
	 self::assertEquals($file_content, $content, 'File content are different');

	 return [ $two_sides, $temp_filepath ];
  }

  /**
	* @test
	*
	* @depends write_to_a_file_on_left_side
	* @throws ExpectationFailedException|InvalidArgumentException
	*/
  public function move_file_to_right_side ( array $params ): array
  {
	 /** @var $two_sides Rclone */
	 [ $two_sides, $file_on_left_side ] = $params;

	 $new_place = $this->getRightWorkingDirectory() . '/' . basename($file_on_left_side);
	 $two_sides->moveto($file_on_left_side, $new_place);

	 $right_side      = new Rclone($two_sides->getRightSide());
	 $check_new_place = $right_side->is_file($new_place);
	 self::assertTrue($check_new_place->exists, 'File not moved');
	 if (!$two_sides->isRightSideFolderAgnostic()) {
		self::assertGreaterThan(0, $check_new_place->details->Size, 'File not moved correctly');
	 }

	 return [ $two_sides, $right_side, $new_place ];
  }

  /**
	* @test
	* @depends move_file_to_right_side
	*
	*/
  public function delete_file_on_right_side($params)
  : array
  {
	 /** @var Rclone $two_sides */
	 /** @var Rclone $right_side */
	 [ $two_sides, $right_side, $filepath ] = $params;

	 $file = $right_side->is_file($filepath);
	 self::assertTrue($file->exists, 'File should exist at this point');

	 $right_side->delete($filepath);

	 $file = $right_side->is_file($filepath);

	 self::assertFalse($file->exists, 'This file should not exist anymore');

	 return [ $two_sides, $right_side ];
  }


}
