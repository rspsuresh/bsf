<?php
$dirname = "install/";
if (!file_exists($dirname)) {
	// Directory Exist action will be written here
	header('location: public/');
} else {
	// Directory Exist action will be written here
	//header('location: install/');
}

?>