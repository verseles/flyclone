<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Test;
use Verseles\Flyclone\Providers\LocalProvider;
use Verseles\Flyclone\Providers\UnionProvider;
use Verseles\Flyclone\Rclone;

class UnionProviderTest extends AbstractProviderTest
{
  private LocalProvider $localProviderA;
  private string        $pathA;
  private LocalProvider $localProviderB;
  private string        $pathB;
  
  public function setUp() : void
  {
    $this->setLeftProviderName('union_test');
    $this->working_directory = ''; // The union provider itself is the root
    
    // Setup two distinct local providers
    $this->pathA = sys_get_temp_dir() . '/flyclone_union_A_' . $this->random_string();
    mkdir($this->pathA, 0777, TRUE);
    $this->localProviderA = new LocalProvider('local_union_A');
    
    $this->pathB = sys_get_temp_dir() . '/flyclone_union_B_' . $this->random_string();
    mkdir($this->pathB, 0777, TRUE);
    $this->localProviderB = new LocalProvider('local_union_B');
  }
  
  #[Test]
  public function instantiate_left_provider() : UnionProvider
  {
    // The `upstreams` string for rclone, specifying the named remotes and their paths.
    // This tells the union to use the remote named `local_union_A` at path `$this->pathA`.
    $upstreams = $this->localProviderA->backend($this->pathA) . ' ' . $this->localProviderB->backend($this->pathB);
    
    // Pass the Provider objects so their configs can be merged.
    $unionProvider = new UnionProvider($this->getLeftProviderName(), [
      'upstreams'          => $upstreams,
      'upstream_providers' => [$this->localProviderA, $this->localProviderB],
      'create_policy'      => 'epmfs', // most free space
    ]);
    
    self::assertInstanceOf(UnionProvider::class, $unionProvider);
    return $unionProvider;
  }
  
  #[Test]
  #[Depends('instantiate_with_one_provider')]
  public function test_union_listing(Rclone $rclone) : void
  {
    // Create a file in each upstream
    file_put_contents($this->pathA . '/file_A.txt', 'content A');
    file_put_contents($this->pathB . '/file_B.txt', 'content B');
    
    $listing = $rclone->ls($this->working_directory);
    
    $fileNames = array_map(fn($item) => $item->Path, $listing);
    
    self::assertCount(2, $listing);
    self::assertContains('file_A.txt', $fileNames);
    self::assertContains('file_B.txt', $fileNames);
  }
  
  #[Test]
  #[Depends('instantiate_with_one_provider')]
  public function test_union_writing(Rclone $rclone) : void
  {
    $newFileName = 'new_file_in_union.txt';
    $newContent = 'This file was written to the union.';
    
    // Write a new file to the union
    $rclone->rcat($newFileName, $newContent);
    
    // Verify the file now appears in the listing
    $listing = $rclone->ls($this->working_directory);
    $fileNames = array_map(fn($item) => $item->Path, $listing);
    self::assertContains($newFileName, $fileNames);
    
    // Verify it exists in one of the underlying physical directories
    $existsInA = file_exists($this->pathA . '/' . $newFileName);
    $existsInB = file_exists($this->pathB . '/' . $newFileName);
    self::assertTrue($existsInA || $existsInB, 'New file was not created in any of the upstream providers.');
  }
}