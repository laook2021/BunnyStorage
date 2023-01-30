<?php

use JefferyHuntley\BunnyStorage\BunnyStorage;

include_once dirname(dirname(__FILE__)) . "/vendor/autoload.php";
$storage_key = ""; // here is your app_key, you can find it on the zone dashboard
$zone_name = ""; // there is your zone name
$local_folder = dirname(__FILE__) . DIRECTORY_SEPARATOR . "files";
$storage_path = "test/kk/";
$temp_file_path = $local_folder . DIRECTORY_SEPARATOR . "1.jpg";
$download_folder = $local_folder . DIRECTORY_SEPARATOR . "download";

// new BunnyStorage Object
$bunny_storage = new BunnyStorage($zone_name, $storage_key, BunnyStorage::ENDPOINT_LOS_ANGELES);
$rtns = $bunny_storage->PutObjects([
    'test/kaka/1.jpg' => $local_folder . DIRECTORY_SEPARATOR . "1.jpg",
    'test/kaka/2.jpg' => $local_folder . DIRECTORY_SEPARATOR . "2.jpg",
    'test/kaka/3.jpg' => $local_folder . DIRECTORY_SEPARATOR . "3.jpg",
    'test/kaka/4.jpg' => $local_folder . DIRECTORY_SEPARATOR . "4.jpg",
]);
echo json_encode($rtns) . "\n";
$rtn_de = $bunny_storage->DeleteObjects(["test/kaka/1.jpg", "test/kaka/2.jpg"]);
echo json_encode($rtn_de) . "\n";
//die;
// Test Put an Object
$rtn = $bunny_storage->PutObject($temp_file_path, $stored_file_path = $storage_path . basename($temp_file_path));
echo json_encode($rtn) . "\n";
// Download It
$rtn = $bunny_storage->DownloadObject($stored_file_path, $download_file = $download_folder . DIRECTORY_SEPARATOR . basename($stored_file_path));
echo "the origin file md5:" . md5_file($temp_file_path) . ", the downloaded file md5:" . md5_file($download_file) . "\n";
// try to put Folder single level
$rtn = $bunny_storage->PutFolder($local_folder, $storage_path, "*.jpg", ['2.jpg']);
echo json_encode($rtn) . "\n";
// try to List Folder just Uploaded
$rtn = $bunny_storage->ListStorageObjects($storage_path);
echo json_encode($rtn) . "\n";
// download files
$rtn = $bunny_storage->DownloadFiles([
    $storage_path . DIRECTORY_SEPARATOR . "3.jpg" => $download_folder.DIRECTORY_SEPARATOR.'3.jpg',
    $storage_path . DIRECTORY_SEPARATOR . "4.jpg" => $download_folder.DIRECTORY_SEPARATOR.'4.jpg',
]);
$rtn = $bunny_storage->DeleteObject($stored_file_path);
echo json_encode($rtn) . "\n";
$rtn = $bunny_storage->DeleteFolder(dirname($stored_file_path) . "/");
echo json_encode($rtn) . "\n";