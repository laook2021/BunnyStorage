<?php

namespace JefferyHuntley\BunnyStorage;


class BunnyStorageRequest
{
    public string $path = '';

    public $success_handler = null;

    public string $method = "GET";

    public mixed $upload_file_handler = null;

    public string $upload_file_path = '';

    public int $upload_file_size = 0;

    public mixed $download_to_store_handler = null;

    public string $download_file_path = '';

    public bool $is_folder_operation = false;
    public function __construct(string $path, callable $success_handler, string $method = 'GET')
    {
        $this->path = $path;
        $this->success_handler = $success_handler;
        $this->method = $method;
    }

    public function SetMethod(string $method): static
    {
        $this->method = strtoupper($method);
        return $this;
    }

    public function SetIsFolderOperation(bool $is_folder = false):self{
        $this->is_folder_operation = $is_folder;
        return $this;
    }

    public function SetUploadFile(string $upload_file): static
    {
        if ($this->upload_file_handler) {
            fclose($this->upload_file_handler);
        }
        $this->upload_file_size = filesize($upload_file);
        $this->upload_file_handler = fopen($upload_file, "r");
        $this->upload_file_path = $upload_file;
        return $this;
    }

    public function SetDownloadFile(string $download_file): static
    {
        if ($this->download_to_store_handler) {
            fclose($this->download_to_store_handler);
        }
        if (!file_exists(dirname($download_file))) {
            mkdir(dirname($download_file), octdec("0755"));
        }
        $this->download_to_store_handler = fopen($download_file, "w+");
        $this->download_file_path = $download_file;
        return $this;
    }
}