BunnyStorage
--

> The Bunny Edge Storage Official PHP SDK was written over 3 year ago. the World has Changed.
> 
> I got the biggest problem that files are too much to upload one by one when i decide to migrant files to Bunny Edge Storage.
> 
> This package was written and test in a few hours by myself.

> It is very easy to use. Just Like Code Below
> 
> $bunny_storage = new BunnyStorage($zone_name, $storage_key, BunnyStorage::ENDPOINT_LOS_ANGELES);
> 
> $rtn = $bunny_storage->PutObject($temp_file_path, $stored_file_path = $storage_path . basename($temp_file_path));
> 
> more Options can be found in BunnyStorage Class
> 
> more example can be found in test folder.
> 
> BTW. don't forget to include autoload.php
> 
> Enjoy your Bunny Edge Storage Working and No More Wheels-Making.
