<?php

namespace JefferyHuntley\BunnyStorage;

use JefferyHuntley\BunnyStorage\Exceptions\BunnyCDNStorageAuthenticationException;
use JefferyHuntley\BunnyStorage\Exceptions\BunnyCDNStorageException;
use JefferyHuntley\BunnyStorage\Exceptions\BunnyCDNStorageFileNotFoundException;
use JefferyHuntley\BunnyStorage\Exceptions\BunnyCDNStorageNoSuchRegionException;
use JefferyHuntley\BunnyStorage\Exceptions\BunnyCDNStorageParamsException;
use CurlHandle;
use RuntimeException;

class BunnyStorage
{
    /**
    The name of the storage zone we are working on
     */
    public string $storageZoneName = '';

    /**

     */
    public string $apiAccessKey = ''; // The API access key used for authentication, you can find it in zone-dashboard, the password of ftp so call

    /**
     * @deprecated 2.0 this property is no longer needed
     * @var string
     */
    private string $storageZoneRegion = 'de'; // The storage zone region code

    private string $current_region = '';

    public const ENDPOINT_FALKENSTEIN = 'Falkenstein'; // Falkenstein. where is this city located in? why default?
    public const ENDPOINT_NEW_YORK = 'NewYork'; // New York
    public const ENDPOINT_LOS_ANGELES = 'LosAngeles'; // Los Angeles. I like this place!
    public const ENDPOINT_SINGAPORE = 'Singapore'; // Singapore
    public const ENDPOINT_SYNDNEY = 'Sydney'; // Sydney

    protected array $client_options = [
        'download_file_recursive_folder_create' => true, // create folder recursive when download file if target folder is not existing.
        'download_file_recursive_folder_create_permission' => "0755",
        'upload_concurrent_num' => 5,
        'download_concurrent_num' => 5,
    ];

    protected function GetClientOptions(string $key):mixed{
        return $this->client_options[$key] ?? null;
    }

    public function SetClientOptions(string $key, mixed $value):self{
        $this->client_options[$key] = $value;
        return $this;
    }

    /**
     * the build-in endpoints and
     * @var array|string[]
     */
    private static array $endpoints = [
        self::ENDPOINT_FALKENSTEIN => 'https://storage.bunnycdn.com',
        self::ENDPOINT_NEW_YORK => 'https://ny.storage.bunnycdn.com',
        self::ENDPOINT_LOS_ANGELES => 'https://la.storage.bunnycdn.com',
        self::ENDPOINT_SINGAPORE => 'https://sg.storage.bunnycdn.com',
        self::ENDPOINT_SYNDNEY => 'https://syd.storage.bunnycdn.com',
    ];

    /**
     * you can call this method to change or append endpoint if some day bunny cdn storage add new endpoint or change the endpoint url
     * @param string $endpoint_name
     * @param string $endpoint_url
     * @return array|string[]
     */
    public static function AppendOrChangeEndpoints(string $endpoint_name, string $endpoint_url):array {
        self::$endpoints[$endpoint_name] = $endpoint_url;
        return self::$endpoints;
    }

    /**
     * Initializes a new instance of the BunnyCDNStorage class
     * @throws BunnyCDNStorageNoSuchRegionException
     */
    public function __construct(string $storageZoneName, string $apiAccessKey, string $storageZoneRegion = self::ENDPOINT_FALKENSTEIN)
    {
        $this->storageZoneName = $storageZoneName;
        $this->apiAccessKey = $apiAccessKey;
        $this->current_region = $storageZoneRegion;
        if (!in_array($storageZoneRegion, array_keys(self::$endpoints))) {
            throw new BunnyCDNStorageNoSuchRegionException("No such Region:{$storageZoneRegion}");
        }
    }

    public function GetCurrentRegion():string{
        return self::$endpoints[$this->current_region];
    }

    /**
     * @param string $url
     * @param bool $is_folder
     * @return string
     */
    protected function GetBuildUrl(string $url, bool $is_folder = false): string
    {

        return trim($this->GetCurrentRegion(), "/") . "/" . ltrim($this->PathNormalization($url, $is_folder), "/");
    }

    /**
     * @param string $path
     * @param bool $is_folder
     * @return string
     */
    protected final function PathNormalization(string $path, bool $is_folder = false):string {
        $origin_path = $path;
        $path = ltrim(preg_replace('#/+#','/', str_replace("\\","/", $path)), "/");
        if (str_contains($path, $this->storageZoneName)) {
            trigger_error(
                "placing the zone_name in path is not suggested.
            the 'auto-zone-name-prepend' activity will be ignored if the zone_name is located in the head of path.",
                E_USER_NOTICE);
        }
        if (str_starts_with($path, $this->storageZoneName)){
            trigger_error("placing zone-name before path is not suggested. the 'auto-zone-name-prepend' activity will not be triggered on this path", E_USER_NOTICE);
        } else {
            // auto-zone-name-prepend
            $path = "{$this->storageZoneName}/{$path}";
        }
        if ($is_folder && !str_ends_with($path, "/")) {
            trigger_error("the given path '{$origin_path}' is not end with '/' but the operation is for folder. 
            you should pay attention on this. the 'auto-slash-append' activity will be triggered", E_USER_NOTICE);
            $path .= "/";
        } else if(!$is_folder && str_ends_with($path, "/")) {
            trigger_error("the given path '{$origin_path}' is end with '/' but the operation is not for folder.
            you should pay attention on this. the 'auto-slash-remove' activity will be triggered", E_USER_NOTICE);
            $path = rtrim($path, "/");
        }
        return $path;
    }

    /**
     * tested
     * @param string $local_folder
     * @param string $storage_folder
     * @param string $wildcard
     * @param array $avoid_upload
     * @param bool $dot_file_upload
     * @return array
     * @throws BunnyCDNStorageAuthenticationException
     * @throws BunnyCDNStorageException
     * @throws BunnyCDNStorageFileNotFoundException
     * @throws BunnyCDNStorageParamsException
     * @todo
     */
    public function PutFolder(string $local_folder, string $storage_folder, string $wildcard = '*', array $avoid_upload = [], bool $dot_file_upload = false): array
    {
        $requests = [];
        $rtn = [];

        foreach(glob(rtrim($local_folder, "/")."/{$wildcard}") as $file_path) {
            if (count($requests) >= $this->GetClientOptions("upload_concurrent_num")) {
                $this->MultiHttpRequest($requests);
                $requests = [];
            }
            if (str_starts_with($file_path, ".") && !$dot_file_upload) { // in default hidden file will not be uploaded
                continue;
            }
            if (in_array(basename($file_path), $avoid_upload)) { // some file should not upload
                continue;
            }
            $requests[] = (new BunnyStorageRequest(
                trim($storage_folder, "/") . "/" . basename($file_path)))->SetMethod("PUT")->SetUploadFile($file_path);

        }

        $this->MultiHttpRequest($requests, function(string|null $content, CurlHandle $handler)use(&$rtn){
            $rtn[] = curl_getinfo($handler,  CURLINFO_EFFECTIVE_URL);
        });
        return $rtn;
    }

    /**
     * tested
     * @param string $storage_folder
     * @return mixed
     * @throws BunnyCDNStorageAuthenticationException
     * @throws BunnyCDNStorageException
     * @throws BunnyCDNStorageFileNotFoundException
     * @throws BunnyCDNStorageParamsException
     */
    public function DeleteFolder(string $storage_folder): mixed
    {
        $rtn = null;
        $bunny_request = (new BunnyStorageRequest(
            $storage_folder))->SetIsFolderOperation(true)->SetMethod("DELETE");
        $this->MultiHttpRequest([$bunny_request], function(string|null $content, CurlHandle $handler)use(&$rtn){
            $rtn = json_decode($content, true);
        });

        return $rtn;
    }

    public function DeleteFiles(array $files) {

    }

    /**
     * tested
     * @param array $files
     * @return array
     * @throws BunnyCDNStorageAuthenticationException
     * @throws BunnyCDNStorageException
     * @throws BunnyCDNStorageFileNotFoundException
     * @throws BunnyCDNStorageParamsException
     */
    public function DownloadFiles(array $files): array
    {
        $rtn = [];
        $requests = [];
        foreach ($files as $storage_path => $local_path) {
            if (count($requests) >= $this->GetClientOptions("download_concurrent_num")) {
                $this->MultiHttpRequest($requests);
                $requests = [];
            }
            $requests[] = (new BunnyStorageRequest(
                $storage_path))->SetDownloadFile($local_path);
        }
        $this->MultiHttpRequest($requests, function (string|null $content, CurlHandle $handler) use (&$rtn) {
            $rtn[] = curl_getinfo($handler, CURLINFO_EFFECTIVE_URL);
        });
        return $rtn;
    }

    /**
     * tested
     * @param string $path
     * @return mixed
     * @throws BunnyCDNStorageAuthenticationException
     * @throws BunnyCDNStorageException
     * @throws BunnyCDNStorageFileNotFoundException
     * @throws BunnyCDNStorageParamsException
     */
    public function ListStorageObjects(string $path): mixed
    {
        $rtn = null;
        $bunny_request = (new BunnyStorageRequest(
            $path))->SetIsFolderOperation(true);
        $this->MultiHttpRequest([$bunny_request], function(string|null $content, CurlHandle $handler)use(&$rtn){
            $rtn = json_decode($content, true);
        });
        return $rtn;
    }

    /**
     * tested
     * @param string $file_path
     * @param string $storage_path
     * @return mixed
     * @throws BunnyCDNStorageAuthenticationException
     * @throws BunnyCDNStorageException
     * @throws BunnyCDNStorageFileNotFoundException
     * @throws BunnyCDNStorageParamsException
     */
    public function PutObject(string $file_path, string $storage_path): mixed
    {
        $bunny_request = (new BunnyStorageRequest(
            $storage_path))->SetMethod("PUT")->SetUploadFile($file_path);
        $this->MultiHttpRequest([$bunny_request], function(string|null $content, CurlHandle $handler)use(&$rtn){
            $rtn = json_decode($content, true);
        });
        return $rtn;
    }

    /**
     * @param array $files_and_its_path
     * @return mixed
     * @throws BunnyCDNStorageAuthenticationException
     * @throws BunnyCDNStorageException
     * @throws BunnyCDNStorageFileNotFoundException
     * @throws BunnyCDNStorageParamsException
     */
    public function PutObjects(array $files_and_its_path): mixed
    {
        $bunny_requests = [];
        $rtn = [];
        foreach ($files_and_its_path as $file_storage_path => $file_local_path) {
            if (!file_exists($file_local_path)) {
                throw new BunnyCDNStorageFileNotFoundException("the File '{$file_local_path}' dose not Exist");
            }
            $bunny_requests[] = (new BunnyStorageRequest(
                $file_storage_path))->SetMethod("PUT")->SetUploadFile($file_local_path);
        }
        $this->MultiHttpRequest($bunny_requests, function(string|null $content, CurlHandle $handler)use(&$rtn){
            $rtn[curl_getinfo($handler, CURLINFO_EFFECTIVE_URL)] = json_decode($content, true);
        });
        return $rtn;
    }

    /**
     * tested
     * @param string $storage_path
     * @return mixed
     * @throws BunnyCDNStorageAuthenticationException
     * @throws BunnyCDNStorageException
     * @throws BunnyCDNStorageFileNotFoundException
     * @throws BunnyCDNStorageParamsException
     */
    public function DeleteObject(string $storage_path): mixed
    {
        $bunny_request = (new BunnyStorageRequest(
            $storage_path))->SetMethod("DELETE");
        $this->MultiHttpRequest([$bunny_request], function(string|null $content, CurlHandle $handler)use(&$rtn){
            $rtn = json_decode($content,true);
        });
        return $rtn;
    }

    public function DeleteObjects(array $object_keys) {
        $bunny_requests = [];
        foreach ($object_keys as $object_key) {
            $bunny_requests[] = (new BunnyStorageRequest(
                $object_key))->SetMethod("DELETE");
        }
        $this->MultiHttpRequest($bunny_requests, function(string|null $content, CurlHandle $handler)use(&$rtn){
            $rtn[curl_getinfo($handler, CURLINFO_EFFECTIVE_URL)] = json_decode($content,true);
        });
        return $rtn;
    }

    /**
     * tested
     * @param string $storage_path
     * @param string $download_path
     * @return mixed
     * @throws BunnyCDNStorageAuthenticationException
     * @throws BunnyCDNStorageException
     * @throws BunnyCDNStorageFileNotFoundException
     * @throws BunnyCDNStorageParamsException
     */
    public function DownloadObject(string $storage_path, string $download_path): mixed
    {
        $bunny_request = (new BunnyStorageRequest(
            $storage_path))->SetDownloadFile($download_path);
        $this->MultiHttpRequest([$bunny_request], function(string|null $content, CurlHandle $handler)use(&$rtn){
            $rtn = json_decode($content?:"", true);
        });
        return $rtn;
    }

    /**
     * @param array $requests
     * @param callable|null $on_success
     * @param callable|null $on_failed
     * @return void
     * @throws BunnyCDNStorageException
     * @throws BunnyCDNStorageParamsException
     */
    protected function MultiHttpRequest(array $requests, ?callable $on_success = null, ?callable $on_failed = null): void
    {
        $curl_multiple_wraper = new CurlMultipleWraper();

        $rtn_collect = [];
        /**
         * @var BunnyStorageRequest $request
         */
        foreach ($requests as $request) {
            $curl_multiple_wraper->CurlSetting(function(CurlHandle $handle)use($request, &$rtn_collect){
                $rtn_collect[] = $request;
                return $this->CurlOptSetter($handle, $request);
            });
        }

        $curl_multiple_wraper->Await(function(string|null $content, CurlHandle $handler)use($on_success){
            (!$on_success) ?: $on_success($content, $handler);
        }, function(CurlHandle $handle)use($on_failed){
            $this->Failed($handle, $on_failed ?: function(CurlHandle $handle){
                throw match ($code = curl_getinfo($handle, CURLINFO_HTTP_CODE)){
                    404 => new BunnyCDNStorageFileNotFoundException(curl_getinfo($handle,  CURLINFO_EFFECTIVE_URL)),
                    401 => new BunnyCDNStorageAuthenticationException($this->storageZoneName, $this->apiAccessKey),
                    default => new BunnyCDNStorageException("http response code error:{$code}"),
                };
            });
        }, function(CurlHandle $handle){
            throw new BunnyCDNStorageException("An unknown error has occured during the request. Status code: " . curl_errno($handle));
        });
    }

    /**
     * @param CurlHandle $handle
     * @param BunnyStorageRequest $request
     * @return CurlHandle
     * @throws BunnyCDNStorageParamsException
     */
    protected function CurlOptSetter(CurlHandle $handle, BunnyStorageRequest $request):CurlHandle{
        if ($request->upload_file_handler && !is_resource($request->upload_file_handler)) {
            throw new BunnyCDNStorageParamsException("params_error:upload file need stream resource");
        }
        if ($request->download_to_store_handler && !is_resource($request->download_to_store_handler)) {
            throw new BunnyCDNStorageParamsException("params_error:down file need stream resource");
        }
        if ($request->download_to_store_handler) {
            if (!file_exists($download_dir = dirname($request->download_file_path)) && $this->GetClientOptions("download_file_recursive_folder_create")) {
                mkdir($download_dir, octdec($this->GetClientOptions("download_file_recursive_folder_create_permission")), recursive: true);
            } elseif (!file_exists($download_dir = dirname($request->download_file_path))) {
                throw new BunnyCDNStorageParamsException("params_error:recursive download file folder create can not be created. if needed please set client options 'download_file_recursive_folder_create' as true");
            }
            if (!is_dir($download_dir)) {
                throw new BunnyCDNStorageParamsException("params_error:the target folder '{$download_dir}' is not a folder");
            }
            if (!is_writable($download_dir)) {
                throw new BunnyCDNStorageParamsException("params_error:the folder '{$download_dir}' can not be written, please confirm you have enough permission for the folder you try to write");
            }
        }

        curl_setopt($handle, CURLOPT_URL, $this->GetBuildUrl($request->path, $request->is_folder_operation));
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($handle, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($handle, CURLOPT_FAILONERROR, 0);

        curl_setopt($handle, CURLOPT_HTTPHEADER, array(
            "AccessKey: {$this->apiAccessKey}",
        ));
        if($request->method == "PUT" && $request->upload_file_handler != NULL)
        {
            curl_setopt($handle, CURLOPT_POST, 1);
            curl_setopt($handle, CURLOPT_UPLOAD, 1);
            curl_setopt($handle, CURLOPT_INFILE, $request->upload_file_handler);
            curl_setopt($handle, CURLOPT_INFILESIZE, $request->upload_file_size);
        } else if($request->method != "GET")
        {
            curl_setopt($handle, CURLOPT_CUSTOMREQUEST, $request->method);
        }


        if($request->method == "GET" && $request->download_to_store_handler != NULL)
        {
            curl_setopt($handle, CURLOPT_FILE, $request->download_to_store_handler);
        }

        return $handle;
    }

    protected function Failed(CurlHandle $handle, ?callable $failed_callback = null){
        $rtn = (!$failed_callback) ?: $failed_callback($handle);
        curl_close($handle);
        return $rtn;
    }

    protected function Success(CurlHandle $handle, ?callable $success_callback = null){
        $rtn = (!$success_callback) ?: $success_callback($handle);
        curl_close($handle);
        return $rtn;
    }
}
