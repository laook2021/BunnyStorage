<?php
namespace JefferyHuntley\BunnyStorage;

use CurlHandle;
use CurlMultiHandle;

class CurlMultipleWraper
{

    protected ?CurlMultiHandle $multi_handler = null;

    protected array $handlers = [];

    public function __construct()
    {
        $this->multi_handler = curl_multi_init();
    }

    public function CurlSetting(callable $handler_setter) {

        $this->handlers[] = $handler = $handler_setter(curl_init());
        curl_multi_add_handle($this->multi_handler, $handler);
        return $this;
    }

    public function Await(?callable $http_success_processor = null, ?callable $http_failed_processor = null, ? callable $curl_error_processor = null){
        $running = 0;
        do{
            curl_multi_exec($this->multi_handler, $running);
        } while ($running > 0);

        /**
         * @var CurlHandle|false $handler
         */
        foreach ($this->handlers as $handler) {
            if (curl_errno($handler)){
                (!$curl_error_processor) ?: $curl_error_processor($handler);
            } else {
                $responseCode = curl_getinfo($handler, CURLINFO_HTTP_CODE);
                if ($responseCode < 200 || $responseCode >  299) {
                    (!$http_failed_processor) ?: $http_failed_processor($handler);
                } else {
                    (!$http_success_processor) ?: $http_success_processor(curl_multi_getcontent($handler), $handler);
                }

            }

            curl_multi_remove_handle($this->multi_handler, $handler);
            (!$handler) ?: curl_close($handler); // try to close the handler if client did not close it
        }
        curl_multi_close($this->multi_handler);
    }
}