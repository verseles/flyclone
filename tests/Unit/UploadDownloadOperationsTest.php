<?php

declare(strict_types=1);

namespace Verseles\Flyclone\Test\Unit;

use Verseles\Flyclone\Providers\SFtpProvider;

// Usaremos SFTP como o provedor "remoto"
use Verseles\Flyclone\Rclone;
use PHPUnit\Framework\ExpectationFailedException;
use SebastianBergmann\RecursionContext\InvalidArgumentException;

/**
 * Testa as operações de upload_file e download_to_local da classe Rclone.
 * Este teste utiliza SFTPProvider como o provedor "remoto" (configurado como left_side na instância Rclone)
 * para simular interações com um servidor externo.
 */
class UploadDownloadOperationsTest extends AbstractProviderTest
{
  /**
   * Configura o ambiente de teste antes de cada método de teste.
   * Define o nome do provedor e o diretório de trabalho para o SFTP.
   *
   * @return void
   * @throws ExpectationFailedException
   * @throws InvalidArgumentException
   */
  public function setUp() : void
  {
    // Define o nome do disco para o provedor SFTP. Este será o 'left_side' da instância Rclone.
    $this->setLeftProviderName('sftp_updown_disk');
    // Define o diretório de trabalho base no servidor SFTP para este conjunto de testes.
    // Um nome aleatório é usado para evitar conflitos entre execuções de teste.
    $this->working_directory = '/upload/flyclone_tests_updown/' . $this->random_string();
    
    // Garante que o nome do provedor foi configurado corretamente.
    self::assertEquals('sftp_updown_disk', $this->getLeftProviderName());
  }
  
  /**
   * Instancia o provedor SFTP que será usado como o 'left_side' (remoto) na instância Rclone.
   * As credenciais e configurações do SFTP são obtidas de variáveis de ambiente.
   * Este método é um produtor de dependência para outros testes.
   *
   * @test
   * @return SFtpProvider A instância configurada do SFtpProvider.
   */
  public function instantiate_left_provider() : SFtpProvider
  {
    $sftpProvider = new SFtpProvider($this->getLeftProviderName(), [
      'HOST' => $_ENV['SFTP_HOST'],
      'USER' => $_ENV['SFTP_USER'],
      'PASS' => Rclone::obscure($_ENV['SFTP_PASS']), // A senha é ofuscada usando Rclone::obscure
      'PORT' => $_ENV['SFTP_PORT'],
    ]);
    
    self::assertInstanceOf(SFtpProvider::class, $sftpProvider);
    return $sftpProvider;
  }
  
  /**
   * Testa o ciclo completo de upload de um arquivo local para o SFTP e, em seguida,
   * o download desse arquivo do SFTP para um novo local.
   *
   * @param Rclone $rcloneRemote Instância de Rclone configurada com SFTPProvider como left_side.
   *                             Esta instância é fornecida pelo método `instantiate_with_one_provider`
   *                             da classe pai `AbstractProviderTest`.
   *
   * @test
   * @depends instantiate_with_one_provider
   * @return void
   * @throws ExpectationFailedException
   * @throws InvalidArgumentException
   */
  public function test_upload_and_download_file(Rclone $rcloneRemote) : void
  {
    // Etapa 0: Garantir que o diretório de trabalho no SFTP exista.
    // O método mkdir criará o diretório se ele não existir.
    $rcloneRemote->mkdir($this->working_directory);
    $dirCheck = $rcloneRemote->is_dir($this->working_directory);
    self::assertTrue($dirCheck->exists, "Diretório de trabalho '{$this->working_directory}' não pôde ser criado ou verificado no SFTP.");
    
    // Etapa 1: Criar um arquivo local temporário com conteúdo.
    $localTempUploadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flyclone_upload_temp_' . $this->random_string();
    // Cria o diretório temporário local para o arquivo de upload.
    if (!mkdir($localTempUploadDir, 0777, TRUE) && !is_dir($localTempUploadDir)) {
      // @codeCoverageIgnoreStart
      self::fail("Não foi possível criar o diretório temporário local para upload: {$localTempUploadDir}");
      // @codeCoverageIgnoreEnd
    }
    $localFilePath = $localTempUploadDir . DIRECTORY_SEPARATOR . 'test_upload_content.txt';
    $originalContent = 'Conteúdo específico para teste de upload e download - ' . $this->random_string(10);
    // Escreve o conteúdo no arquivo local.
    file_put_contents($localFilePath, $originalContent);
    self::assertFileExists($localFilePath, 'Arquivo local para upload não foi criado.');
    
    // Etapa 2: Definir o caminho do arquivo remoto no SFTP.
    $remoteFilePath = $this->working_directory . DIRECTORY_SEPARATOR . 'uploaded_via_flyclone.txt';
    
    // Etapa 3: Fazer upload do arquivo local para o "remoto" (SFTP).
    // O método `upload_file` utiliza `moveto`, que remove o arquivo local original após o sucesso.
    $uploadSuccess = $rcloneRemote->upload_file($localFilePath, $remoteFilePath);
    self::assertTrue($uploadSuccess, 'Falha ao fazer upload do arquivo para o SFTP.');
    // Verifica se o arquivo local original foi removido, como esperado pelo `moveto`.
    self::assertFileDoesNotExist($localFilePath, 'Arquivo local original ainda existe após upload_file (deveria ter sido movido).');
    
    // Etapa 4: Verificar se o arquivo existe no SFTP e se o conteúdo está correto.
    $fileExistsOnRemote = $rcloneRemote->is_file($remoteFilePath);
    self::assertTrue($fileExistsOnRemote->exists, 'Arquivo não encontrado no SFTP após upload.');
    // Lê o conteúdo do arquivo no SFTP.
    $remoteContent = $rcloneRemote->cat($remoteFilePath);
    self::assertEquals($originalContent, $remoteContent, 'Conteúdo do arquivo no SFTP difere do original.');
    
    // Etapa 5: Fazer download do arquivo do SFTP para um novo local temporário.
    $localTempDownloadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flyclone_download_temp_' . $this->random_string();
    // O método `download_to_local` criará o diretório pai se não existir.
    $downloadedLocalFilePath = $localTempDownloadDir . DIRECTORY_SEPARATOR . 'downloaded_from_sftp.txt';
    
    $downloadResultPath = $rcloneRemote->download_to_local($remoteFilePath, $downloadedLocalFilePath);
    self::assertNotFalse($downloadResultPath, 'Falha ao fazer download do arquivo do SFTP.');
    self::assertEquals($downloadedLocalFilePath, $downloadResultPath, 'Caminho do arquivo baixado não é o esperado.');
    self::assertFileExists($downloadedLocalFilePath, 'Arquivo baixado não encontrado localmente.');
    
    // Etapa 6: Verificar se o conteúdo do arquivo baixado é idêntico ao original.
    $downloadedContent = file_get_contents($downloadedLocalFilePath);
    self::assertEquals($originalContent, $downloadedContent, 'Conteúdo do arquivo baixado difere do original.');
    
    // Etapa 7: Limpeza dos artefatos do teste.
    // Remover diretório temporário local de upload (o arquivo já foi movido).
    if (is_dir($localTempUploadDir)) {
      rmdir($localTempUploadDir);
    }
    // Remover arquivo baixado e seu diretório temporário.
    if (file_exists($downloadedLocalFilePath)) {
      unlink($downloadedLocalFilePath);
    }
    if (is_dir($localTempDownloadDir)) {
      rmdir($localTempDownloadDir);
    }
    // Remover arquivo do "remoto" (SFTP).
    $rcloneRemote->deletefile($remoteFilePath);
    // Tenta remover o diretório de trabalho no SFTP (só funcionará se estiver vazio).
    try {
      $rcloneRemote->rmdir($this->working_directory);
    }
    catch (\Exception $e) {
      // Ignora falha ao remover o diretório, pode não estar vazio ou permissões.
      // Em um cenário real, poderia ser logado.
    }
  }
}