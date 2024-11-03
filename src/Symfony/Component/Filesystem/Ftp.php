<?php

namespace Symfony\Component\Filesystem;

use Exception;

/**
 * Class FtpService
 * 
 * Provides a set of methods to connect to an FTP server, upload and download files, delete files,
 * and manage FTP directories.
 * 
 * @package Symfony\Component\Filesystem
 * @author Younes Zahfouf
 */
class Ftp
{
    private $ftpConnection;
    private $ftpHost;
    private $ftpUsername;
    private $ftpPassword;

    /**
     * FtpService constructor.
     *
     * @param string $ftpHost      FTP server hostname
     * @param string $ftpUsername  FTP server username
     * @param string $ftpPassword  FTP server password
     */
    public function __construct(string $ftpHost, string $ftpUsername, string $ftpPassword)
    {
        $this->ftpHost = $ftpHost;
        $this->ftpUsername = $ftpUsername;
        $this->ftpPassword = $ftpPassword;
    }

    /**
     * Connects to the FTP server and logs in.
     *
     * @return resource FTP connection resource
     * @throws Exception if connection or login fails
     */
    public function connect()
    {
        $this->ftpConnection = ftp_connect($this->ftpHost);

        if (!$this->ftpConnection) {
            throw new Exception('Could not connect to FTP server');
        }

        $loginResult = ftp_login($this->ftpConnection, $this->ftpUsername, $this->ftpPassword);

        if (!$loginResult) {
            throw new Exception('Could not log in to FTP server');
        }

        ftp_pasv($this->ftpConnection, true); // Enable passive mode
        return $this->ftpConnection;
    }

    /**
     * Uploads a file to the FTP server.
     *
     * @param string $remoteFile Path on the server where the file will be uploaded
     * @param string $localFile  Path to the local file to upload
     * @return bool True if upload succeeds
     * @throws Exception if file upload fails
     */
    public function uploadFile(string $remoteFile, string $localFile)
    {
        $upload = ftp_put($this->ftpConnection, $remoteFile, $localFile, FTP_BINARY);
        if (!$upload) {
            throw new Exception('File upload failed');
        }
        return true;
    }

    /**
     * Downloads a file from the FTP server.
     *
     * @param string $remoteFile Path to the file on the server
     * @param string $localFile  Local path where the file will be saved
     * @return bool True if download succeeds
     * @throws Exception if file download fails
     */
    public function downloadFile(string $remoteFile, string $localFile)
    {
        $download = ftp_get($this->ftpConnection, $remoteFile, $localFile, FTP_BINARY);
        if (!$download) {
            throw new Exception('File download failed');
        }
        return true;
    }

    /**
     * Downloads all files from a remote directory to a local directory.
     *
     * @param string $remoteDir Path to the remote directory
     * @param string $localDir  Local directory path where files will be saved
     * @throws Exception if listing or downloading files fails
     */
    public function downloadDirectory(string $remoteDir, string $localDir)
    {
        if (!is_dir($localDir)) {
            mkdir($localDir, 0777, true);
        }

        $fileList = ftp_nlist($this->ftpConnection, $remoteDir);
        if ($fileList === false) {
            throw new Exception("Could not list files in directory: $remoteDir");
        }

        foreach ($fileList as $file) {
            $remoteFilePath = $file;
            $localFilePath = $localDir . '/' . basename($file);
            if (!$this->isDirectory($file)) {
                try {
                    ftp_get($this->ftpConnection, $localFilePath, $remoteFilePath, FTP_BINARY);
                } catch (\Throwable $th) {
                    echo $th->getMessage();
                }
            }
        }
    }

    /**
     * Deletes a file on the FTP server.
     *
     * @param string $file Path to the file on the server
     * @throws Exception if file deletion fails
     */
    public function deleteFile(string $file)
    {
        $delete = ftp_delete($this->ftpConnection, $file);
        if ($delete === false) {
            throw new Exception("Could not delete file: $file");
        }
    }

    /**
     * Retrieves a list of files in a remote directory.
     *
     * @param string $remoteDir Path to the remote directory
     * @return array List of files in the directory
     */
    public function scanDir(string $remoteDir): array
    {
        $fileList = ftp_nlist($this->ftpConnection, $remoteDir);
        return array_filter($fileList, fn($path) => !in_array($path, ["$remoteDir/.", "$remoteDir/.."]));
    }

    /**
     * Deletes all files in a remote directory.
     *
     * @param string $remoteDir Path to the remote directory
     */
    public function deleteAllFiles(string $remoteDir)
    {
        $files = $this->scanDir($remoteDir);
        foreach ($files as $file) {
            $this->deleteFile($file);
        }
    }

    /**
     * Checks if the specified path is a directory.
     *
     * @param string $remoteFile Path to check
     * @return bool True if the path is a directory
     */
    private function isDirectory(string $remoteFile): bool
    {
        $currentDir = ftp_pwd($this->ftpConnection);
        if (@ftp_chdir($this->ftpConnection, $remoteFile)) {
            ftp_chdir($this->ftpConnection, $currentDir);
            return true;
        }
        return false;
    }

    /**
     * Closes the FTP connection.
     */
    public function closeConnection()
    {
        if ($this->ftpConnection) {
            ftp_close($this->ftpConnection);
        }
    }
}
