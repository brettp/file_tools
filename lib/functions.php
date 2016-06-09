<?php
/**
 * All helper functions are bundled here
 */
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Get the extension of an ElggFile
 *
 * @param ElggFile $file the file to check
 *
 * @return string
 */
function file_tools_get_file_extension($file) {
	$result = '';

	if ($file->getSubtype() == 'file') {
		if ($filename = $file->getFilename()) {
			$exploded_filename = explode('.', $filename);

			$result = end($exploded_filename);
		}
	}

	return strtolower($result);
}

/**
 * Read a folder structure for a zip file
 *
 * @param ElggObject $folder  the folder to read
 * @param string     $prepend current prefix
 *
 * @return array
 */
function file_tools_get_zip_structure($folder, $prepend) {
	$entries = array();

	if ($prepend) {
		$prepend .= '/';
	}

	if (!$folder) {
		$parent_guid = 0;
	} else {
		$parent_guid = $folder->getGUID();
	}

	$options = array(
		"type" => "object",
		"subtype" => FILE_TOOLS_SUBTYPE,
		"limit" => false,
		"metadata_name" => "parent_guid",
		"metadata_value" => $parent_guid,
	);

	// voor de hoogste map de sub bestanden nog ophalen
	if ($entities = elgg_get_entities_from_metadata($options)) {
		foreach ($entities as $subfolder) {
			$title = $prepend . $subfolder->title;
			$entries[] = array(
				'directory' => $title,
				'files' => file_tools_has_files($subfolder->getGUID())
			);

			$entries = array_merge($entries, file_tools_get_zip_structure($subfolder, $title));
		}
	}

	return $entries;
}

/**
 * Check if a folder has files
 *
 * @param ElggObject $folder the folder to check
 *
 * @return bool|array
 */
function file_tools_has_files($folder) {
	$files_options = array(
		"type" => "object",
		"subtype" => "file",
		"limit" => false,
		//"container_guid" => get_loggedin_userid(),
		"relationship" => FILE_TOOLS_RELATIONSHIP,
		"relationship_guid" => $folder,
		"inverse_relationship" => false
	);

	$file_guids = array();

	if ($files = elgg_get_entities_from_relationship($files_options)) {
		foreach ($files as $file) {
			$file_guids[] = $file->getGUID();
		}

		return $file_guids;
	}

	return false;
}

/**
 * Get the folders in a container
 *
 * @param int $container_guid the container to check
 *
 * @return bool|ElggObject[]
 */
function file_tools_get_folders($container_guid = 0) {
	$result = false;

	if (empty($container_guid)) {
		$container_guid = elgg_get_page_owner_guid();
	}

	if (empty($container_guid)) {
		return false;
	}

	$options = array(
		"type" => "object",
		"subtype" => FILE_TOOLS_SUBTYPE,
		"container_guid" => $container_guid,
		"limit" => false
	);

	$folders = elgg_get_entities($options);
	if (!$folders) {
		return false;
	}

	$parents = array();
	foreach ($folders as $folder) {
		$parent = get_entity($folder->parent_guid);

		if (elgg_instanceof($parent, 'object', FILE_TOOLS_SUBTYPE)) {
			$parent_guid = $parent->getGUID();
		} else {
			$parent_guid = 0;
		}

		if (!array_key_exists($parent_guid, $parents)) {
			$parents[$parent_guid] = array();
		}

		$parents[$parent_guid][] = $folder;
	}

	$result = file_tools_sort_folders($parents, 0);

	return $result;
}

/**
 * Make folder select options
 *
 * @param array $folders folders to make the options for
 * @param int   $depth   current depth
 *
 * @return string
 */
function file_tools_build_select_options($folders, $depth = 0) {
	$result = array();

	if (!empty($folders)) {
		foreach ($folders as $index => $level) {
			/**
			 * $level contains
			 * folder: the folder on this level
			 * children: potential children
			 *
			 */
			if ($folder = elgg_extract("folder", $level)) {
				$result[$folder->getGUID()] = str_repeat("-", $depth) . $folder->title;
			}

			if ($childen = elgg_extract("children", $level)) {
				$result += file_tools_build_select_options($childen, $depth + 1);
			}
		}
	}

	return $result;
}

/**
 * Get folder selection options for widgets
 *
 * @param array  $folder       the folder to create for
 * @param string $internalname the name of the input field
 * @param array  $selected     the current selected values
 *
 * @return string
 */
function file_tools_build_widget_options($folder, $internalname = "", $selected = array()) {
	$result = "";

	if (is_array($folder) && !array_key_exists("children", $folder)) {
		foreach ($folder as $folder_item) {
			$result .= "<ul>";
			$result .= file_tools_build_widget_options($folder_item, $internalname, $selected);
			$result .= "</ul>";
		}
	} else {
		$folder_item = $folder["folder"];

		$result .= "<li>";
		if (in_array($folder_item->getGUID(), $selected)) {
			$result .= "<input type='checkbox' name='" .
				$internalname .
				"' value='" .
				$folder_item->getGUID() .
				"' checked='checked'> " .
				$folder_item->title;
		} else {
			$result .= "<input type='checkbox' name='" .
				$internalname .
				"' value='" .
				$folder_item->getGUID() .
				"'> " .
				$folder_item->title;
		}

		if (!empty($folder["children"])) {
			$result .= file_tools_build_widget_options($folder["children"], $internalname, $selected);
		}
		$result .= "</li>";
	}

	return $result;
}

/**
 * Folder folders by their order
 *
 * @param array $folders     the folders to sort
 * @param int   $parent_guid the parent to sort for
 *
 * @return bool|array
 */
function file_tools_sort_folders($folders, $parent_guid = 0) {
	if (!array_key_exists($parent_guid, $folders)) {
		return false;
	}

	$result = array();

	foreach ($folders[$parent_guid] as $subfolder) {
		$children = file_tools_sort_folders($folders, $subfolder->getGUID());

		$order = $subfolder->order;
		if (empty($order)) {
			$order = 0;
		}

		while (array_key_exists($order, $result)) {
			$order++;
		}

		$result[$order] = array(
			"folder" => $subfolder,
			"children" => $children
		);
	}

	ksort($result);

	return $result;
}

/**
 * Get the subfolders of a folder
 *
 * @param ElggObject $folder the folder to get the subfolders for
 * @param bool       $list   output a list (default: false)
 *
 * @return bool|array|string
 */
function file_tools_get_sub_folders($folder = false, $list = false) {
	$result = false;

	$options = array(
		"type" => "object",
		"subtype" => FILE_TOOLS_SUBTYPE,
		"limit" => false,
		"full_view" => false,
		"pagination" => false
	);

	$page_owner = elgg_get_page_owner_entity();

	if ($page_owner instanceof ElggGroup && !empty($page_owner->file_tools_sort)) {
		// Each group has its own sorting settings
		$order_by = $page_owner->file_tools_sort;
		$order_direction = $page_owner->file_tools_sort_direction;

		$dbprefix = get_config('dbprefix');
		$options['joins'] = array("JOIN {$dbprefix}objects_entity oe ON e.guid = oe.guid");
		$options['order_by'] = "{$order_by} {$order_direction}";
	} else {
		// Default to sorting by 'order' metadata
		$options["order_by_metadata"] = array(
			'name' => 'order',
			'direction' => 'ASC',
			'as' => 'integer',
		);
	}

	if (!empty($folder) && elgg_instanceof($folder, "object", FILE_TOOLS_SUBTYPE)) {
		$options['container_guid'] = $folder->getContainerGUID();
		$parent_guid = $folder->getGUID();
	} else {
		$options['container_guid'] = $page_owner->guid;
		$parent_guid = 0;
	}

	$options["metadata_name"] = "parent_guid";
	$options["metadata_value"] = $parent_guid;

	if ($list) {
		$folders = elgg_list_entities_from_metadata($options);
	} else {
		$folders = elgg_get_entities_from_metadata($options);
	}

	if ($folders) {
		$result = $folders;
	}

	return $result;
}

/**
 * Create a folder menu
 *
 * @param array $folders the folders to create the menu for
 *
 * @return bool|ElggMenuItem[]
 */
function file_tools_make_menu_items($folders) {
	$result = false;

	if (!$folders) {
		return false;
	}

	$result = array();

	foreach ($folders as $index => $level) {
		$folder = elgg_extract("folder", $level);

		if (!$folder) {
			continue;
		}

		$folder_title = $folder->title;
		if (empty($folder_title)) {
			$folder_title = elgg_echo("untitled");
		}

		$folder_menu = ElggMenuItem::factory(array(
			"name" => "folder_" . $folder->getGUID(),
			"text" => $folder_title,
			"href" => "#" . $folder->getGUID(),
			"priority" => $folder->order
		));

		if ($children = elgg_extract("children", $level)) {
			$folder_menu->setChildren(file_tools_make_menu_items($children));
		}

		$result[] = $folder_menu;
	}

	return $result;
}

/**
 * Recursively change the access of subfolders (and files)
 *
 * @param ElggObject $folder       the folder to change the access for
 * @param bool       $change_files include files in this folder (default: false)
 *
 * @return void
 */
function file_tools_change_children_access($folder, $change_files = false) {
	if (!elgg_instanceof($folder, 'object', FILE_TOOLS_SUBTYPE)) {
		return;
	}

	// get children folders
	$options = array(
		"type" => "object",
		"subtype" => FILE_TOOLS_SUBTYPE,
		"container_guid" => $folder->getContainerGUID(),
		"limit" => false,
		"metadata_name" => "parent_guid",
		"metadata_value" => $folder->getGUID()
	);

	if ($children = elgg_get_entities_from_metadata($options)) {
		foreach ($children as $child) {
			$child->access_id = $folder->access_id;
			$child->save();

			file_tools_change_children_access($child, $change_files);
		}
	}

	if ($change_files) {
		// change access on files in this folder
		file_tools_change_files_access($folder);
	}
}

/**
 * Change the access of all file in a folder
 *
 * @param ElggObject $folder the folder to change the file access for
 *
 * @return void
 */
function file_tools_change_files_access($folder) {
	if (!elgg_instanceof($folder, 'object', FILE_TOOLS_SUBTYPE)) {
		return;
	}

	// change access on files in this folder
	$options = array(
		"type" => "object",
		"subtype" => "file",
		"container_guid" => $folder->getContainerGUID(),
		"limit" => false,
		"relationship" => FILE_TOOLS_RELATIONSHIP,
		"relationship_guid" => $folder->getGUID()
	);

	if ($files = elgg_get_entities_from_relationship($options)) {
		foreach ($files as $file) {
			$file->access_id = $folder->access_id;
			$file->save();
		}
	}
}

/**
 * Get the allowed extensions for uploading
 *
 * @param bool $zip return zip upload dialog format
 *
 * @return bool|string
 */
function file_tools_allowed_extensions($zip = false) {

	$allowed_extensions_settings = elgg_get_plugin_setting('allowed_extensions', 'file_tools');
	$allowed_extensions_settings = string_to_tag_array($allowed_extensions_settings);

	if (!empty($allowed_extensions_settings)) {
		$result = $allowed_extensions_settings;
	} else {
		$result = array(
			'txt',
			'jpg',
			'jpeg',
			'png',
			'bmp',
			'gif',
			'pdf',
			'doc',
			'docx',
			'xls',
			'xlsx',
			'ppt',
			'pptx',
			'odt',
			'ods',
			'odp'
		);
	}

	if (!$zip) {
		return $result;
	}

	$result = implode(";*.", $result);

	return "*." . $result;
}

/**
 * Check for unique folder names
 *
 * @param string $title          the title to check
 * @param int    $container_guid the container to limit the search to
 * @param int    $parent_guid    optional parent guid
 *
 * @return bool|ElggObject
 */
function file_tools_check_foldertitle_exists($title, $container_guid, $parent_guid = 0) {
	$result = false;

	$entities_options = array(
		'type' => 'object',
		'subtype' => FILE_TOOLS_SUBTYPE,
		'container_guid' => $container_guid,
		'limit' => 1,
		'joins' => array(
			"JOIN " . elgg_get_config("dbprefix") . "objects_entity oe ON e.guid = oe.guid"
		),
		'wheres' => array(
			"oe.title = '" . sanitise_string($title) . "'"
		),
		"order_by_metadata" => array(
			"name" => "order",
			"direction" => "ASC",
			"as" => "integer"
		)
	);

	if (!empty($parent_guid)) {
		$entities_options["metadata_name_value_pairs"] = array("parent_guid" => $parent_guid);
	}

	if ($entities = elgg_get_entities_from_metadata($entities_options)) {
		$result = $entities[0];
	}

	return $result;
}

/**
 * Create folders from a zip file structure
 *
 * @param zip_entry $zip_entry      the zip file entry
 * @param int       $container_guid the container where the folders need to be created
 * @param int       $parent_guid    the parent folder
 *
 * @return void
 */
function file_tools_create_folders($zip_entry, $container_guid, $parent_guid = 0) {
	$zip_entry_name = zip_entry_name($zip_entry);

	// remove any files from the path to make sure we're working with just the dir
	if (substr($zip_entry_name, -1) != '/') {
		$filename = basename($zip_entry_name);
		$zip_base = str_replace($filename, "", $zip_entry_name);
	} else {
		$zip_base = $zip_entry_name;
	}

	$zdir = rtrim($zip_base, '/');

	// if entry is at the root, don't try creating folders
	if (!$zdir) {
		return;
	}

	$container_entity = get_entity($container_guid);

	$access_id = get_input("access_id", false);
	if ($access_id === false) {
		if ($parent_guid != 0) {
			$access_id = get_entity($parent_guid)->access_id;
		} else {
			if (elgg_instanceof($container_entity, "group")) {
				$access_id = $container_entity->group_acl;
			} else {
				$access_id = get_default_access($container_entity);
			}
		}
	}

	$sub_folders = explode("/", $zdir);
	$parent = $parent_guid;

	foreach ($sub_folders as $folder) {
		if ($entity = file_tools_check_foldertitle_exists($folder, $container_guid, $parent)) {
			$parent = $entity->getGUID();
		} else {
			$directory = new ElggObject();
			$directory->subtype = FILE_TOOLS_SUBTYPE;
			$directory->owner_guid = elgg_get_logged_in_user_guid();
			$directory->container_guid = $container_guid;

			$directory->access_id = $access_id;

			$directory->title = $folder;
			$directory->parent_guid = $parent;

			$order = elgg_get_entities_from_metadata(array(
				"type" => "object",
				"subtype" => FILE_TOOLS_SUBTYPE,
				"metadata_name_value_pairs" => array(
					"name" => "parent_guid",
					"value" => $parent
				),
				"count" => true
			));

			// @todo I don't think works as expected
			$directory->order = $order;
			$parent = $directory->save();
		}
	}
}

/**
 * @todo Fix this to work with file uploads
 *
 * Unzip an uploaded zip file
 *
 * @param \Symfony\Component\HttpFoundation\File\UploadedFile $file
 * @param array $attrs
 * @return bool
 */
function file_tools_unzip(UploadedFile $file, array $attrs = null) {
	if (!$file || !$container_guid) {
		return false;
	}

	$allowed_extensions = file_tools_allowed_extensions();
	$zipfile = $file->getRealPath();
	$container_entity = get_entity($container_guid);

	if ($access_id === false) {
		if (!empty($parent_guid) &&
			($parent_folder = get_entity($parent_guid)) &&
			elgg_instanceof($parent_folder, "object", FILE_TOOLS_SUBTYPE)
		) {
			$access_id = $parent_folder->access_id;
		} else {
			if (elgg_instanceof($container_entity, "group")) {
				$access_id = $container_entity->group_acl;
			} else {
				$access_id = get_default_access($container_entity);
			}
		}
	}

	// open the zip file
	$zip = zip_open($zipfile);
	while ($zip_entry = zip_read($zip)) {
		// open the zip entry
		zip_entry_open($zip, $zip_entry);

		$zip_entry_name = utf8_encode(zip_entry_name($zip_entry));
		$filename = basename($zip_entry_name);

		// create local folder structure to mirror entry's
		if ($filename != $zip_entry_name) {
			file_tools_create_folders($zip_entry, $container_guid, $parent_guid);
		}

		$folder_array = explode("/", $zip_entry_name);
		$filename = array_pop($folder_array);

		// walk down the folder structure to find the file's parent folder entity
		$parent = $parent_guid;
		foreach ($folder_array as $folder) {
			$entity = file_tools_check_foldertitle_exists($folder, $container_guid, $parent);
			if ($entity) {
				$parent = $entity->getGUID();
			} else {
				break;
			}
		}

		$extension_array = explode('.', $filename);
		$file_extension = array_pop($extension_array);

		if (!in_array(strtolower($file_extension), $allowed_extensions)) {
			continue;
		}

		$prefix = "file/";

		// create the ElggFile
		$file = new ElggFile();
		$file->setFilename($prefix . $filename);
		$file->originalfilename = $filename;

		$file->title = $filename;
		$file->owner_guid = elgg_get_logged_in_user_guid();

		$file->container_guid = $container_guid;
		$file->access_id = $access_id;

		$file->open("write");
		$file->write(zip_entry_read($zip_entry));
		$file->close();

		$mime_type = $file->detectMimeType($file->getFilenameOnFilestore());
		$file->setMimeType($mime_type);
		$file->simpletype = elgg_get_file_simple_type($mime_type);

		set_input('folder_guid', $parent);

		if (!$file->save()) {
			return false;
		}

		file_tools_create_thumbnails($file, $prefix, $filename);

		if (!empty($parent)) {
			add_entity_relationship($parent, FILE_TOOLS_RELATIONSHIP, $file->getGUID());
		}

		zip_entry_close($zip_entry);
	}

	zip_close($zip);

	return true;
}

function file_tools_create_thumbnails(ElggFile $file, $prefix, $filestorename) {
	// remove any old thumbnails if file isn't an image
	if ($file->simpletype != 'image') {
		if ($file->icontime) {
			unset ($file->icontime);
		}

		$thumb = new ElggFile();

		$thumb->setFilename($prefix . "thumb" . $filestorename);
		$thumb->delete();
		unset($file->thumbnail);

		$thumb->setFilename($prefix . "smallthumb" . $filestorename);
		$thumb->delete();
		unset($file->smallthumb);

		$thumb->setFilename($prefix . "largethumb" . $filestorename);
		$thumb->delete();
		unset($file->largethumb);
		return false;
	}
	$file->icontime = time();

	$thumbnail = get_resized_image_from_existing_file($file->getFilenameOnFilestore(), 60, 60, true);
	if ($thumbnail) {
		$thumb = new ElggFile();
		$thumb->setMimeType($file->getMimeType());

		$thumb->setFilename($prefix . "thumb" . $filestorename);
		$thumb->open("write");
		$thumb->write($thumbnail);
		$thumb->close();

		$file->thumbnail = $prefix . "thumb" . $filestorename;
		unset($thumbnail);
	}

	$thumbsmall = get_resized_image_from_existing_file($file->getFilenameOnFilestore(), 153, 153, true);
	if ($thumbsmall) {
		$thumb->setFilename($prefix . "smallthumb" . $filestorename);
		$thumb->open("write");
		$thumb->write($thumbsmall);
		$thumb->close();
		$file->smallthumb = $prefix . "smallthumb" . $filestorename;
		unset($thumbsmall);
	}

	$thumblarge = get_resized_image_from_existing_file($file->getFilenameOnFilestore(), 600, 600, false);
	if ($thumblarge) {
		$thumb->setFilename($prefix . "largethumb" . $filestorename);
		$thumb->open("write");
		$thumb->write($thumblarge);
		$thumb->close();
		$file->largethumb = $prefix . "largethumb" . $filestorename;
		unset($thumblarge);
	}
}

/**
 * Helper function to check if we use a folder structure
 * Result is cached to increase performance
 *
 * @return bool
 */
function file_tools_use_folder_structure() {
	static $result;

	if (!isset($result)) {
		$result = false;

		// this plugin setting has a typo, should be use_folder_struture
		// @todo: update the plugin setting name
		if (elgg_get_plugin_setting("user_folder_structure", "file_tools") == "yes") {
			$result = true;
		}
	}

	return $result;
}

/**
 * Add a folder to a zip file
 *
 * @param ZipArchive &$zip_archive the zip file to add files/folder to
 * @param ElggObject $folder       the folder to add
 * @param string     $folder_path  the path of the current folder
 *
 * @return void
 */
function file_tools_add_folder_to_zip(ZipArchive &$zip_archive, ElggObject $folder, $folder_path = "") {

	if (!empty($zip_archive) && !empty($folder) && elgg_instanceof($folder, "object", FILE_TOOLS_SUBTYPE)) {
		$folder_title = elgg_get_friendly_title($folder->title);

		$zip_archive->addEmptyDir($folder_path . $folder_title);
		$folder_path .= $folder_title . DIRECTORY_SEPARATOR;

		$file_options = array(
			"type" => "object",
			"subtype" => "file",
			"limit" => false,
			"relationship" => FILE_TOOLS_RELATIONSHIP,
			"relationship_guid" => $folder->getGUID()
		);

		// add files from this folder to the zip
		if ($files = elgg_get_entities_from_relationship($file_options)) {
			foreach ($files as $file) {
				// check if the file exists
				if ($zip_archive->statName($folder_path . $file->originalfilename) === false) {
					// doesn't exist, so add
					$zip_archive->addFile($file->getFilenameOnFilestore(), $folder_path . $file->originalfilename);
				} else {
					// file name exists, so create a new one
					$ext_pos = strrpos($file->originalfilename, ".");
					$file_name = substr($file->originalfilename, 0, $ext_pos) .
						"_" .
						$file->getGUID() .
						substr($file->originalfilename, $ext_pos);

					$zip_archive->addFile($file->getFilenameOnFilestore(), $folder_path . $file_name);
				}
			}
		}

		// check if there are subfolders
		$folder_options = array(
			"type" => "object",
			"subtype" => FILE_TOOLS_SUBTYPE,
			"limit" => false,
			"metadata_name_value_pairs" => array("parent_guid" => $folder->getGUID())
		);

		if ($sub_folders = elgg_get_entities_from_metadata($folder_options)) {
			foreach ($sub_folders as $sub_folder) {
				file_tools_add_folder_to_zip($zip_archive, $sub_folder, $folder_path);
			}
		}
	}
}

/**
 * Get a readable byte count
 *
 * @return string
 */
function file_tools_get_readable_file_size_limit() {
	return file_tools_get_readable_file_size(elgg_get_ini_setting_in_bytes("upload_max_filesize"));
}

function file_tools_get_readable_file_size($size) {
	$size_units = array("B", "KB", "MB", "GB", "TB", "PB");
	$i = 0;

	while ($size > 1024) {
		$i++;
		$size /= 1024;
	}

	$result = $size . $size_units[$i];

	return $result;
}

/**
 * Get the listing max length from the plugin settings
 *
 * @return int
 */
function file_tools_get_list_length() {
	static $result;

	if (!isset($result)) {
		$result = 50;

		$setting = (int)elgg_get_plugin_setting("list_length", "file_tools");
		if ($setting < 0) {
			$result = false;
		} elseif ($setting > 0) {
			$result = $setting;
		}
	}

	return $result;
}

/**
 * @param \Symfony\Component\HttpFoundation\File\File $file
 * @param array|null                                  $attrs
 * @param \ElggEntity|null                            $elgg_file
 *
 * @return bool|\ElggFile
 * @throws \IOException
 * @throws \InvalidParameterException
 */
function file_tools_upload_to_elggfile(File $file, array $attrs = null, \ElggEntity $elgg_file = null) {
	$new = false;
	if (!$elgg_file instanceof ElggFile) {
		$elgg_file = new ElggFile();
		$new = true;
	}

	if ($attrs) {
		foreach ($attrs as $k => $v) {
			$elgg_file->{$k} = $v;
		}
	}

	$prefix = "file/";

	// if previous file, delete it
	if (!$new) {
		$filename = $elgg_file->getFilenameOnFilestore();
		if (file_exists($filename)) {
			unlink($filename);
		}

		// use same filename on the disk - ensures thumbnails are overwritten
		$filestorename = $elgg_file->getFilename();
		$filestorename = elgg_substr($filestorename, elgg_strlen($prefix));
	} else if ($file instanceof UploadedFile) {
		$filestorename = elgg_strtolower(time() . $file->getClientOriginalName());
	} else {
		$filestorename = elgg_strtolower(time() . $file->getFilename());
	}

	$elgg_file->setFilename($prefix . $filestorename);
	$elgg_file->originalfilename = ($file instanceof UploadedFile) ? $file->getClientOriginalName() : $file->getFilename();
	$mime_type = $elgg_file->detectMimeType($file->getRealPath());

	$elgg_file->setMimeType($mime_type);
	$elgg_file->simpletype = elgg_get_file_simple_type($mime_type);

	// Open the file to guarantee the directory exists
	$elgg_file->open("write");
	$elgg_file->close();
	$path = dirname($elgg_file->getFilenameOnFilestore());

	$file->move($path, $filestorename);

	if (!$elgg_file->save()) {
		// clean up any saved data
		$elgg_file->delete();
		return false;
	}

	file_tools_create_thumbnails($elgg_file, $prefix, $filestorename);

	return $elgg_file;
}
