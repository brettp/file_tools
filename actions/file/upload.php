<?php
/**
 * Elgg file uploader/edit action
 *
 * @package ElggFile
 */

use Symfony\Component\HttpFoundation\File\UploadedFile;

$title = htmlspecialchars(get_input('title', '', false), ENT_QUOTES, 'UTF-8');
$desc = get_input("description");
$access_id = (int) get_input("access_id");
$container_guid = (int) get_input('container_guid', 0);
$folder_guid = (int) get_input('folder_guid', 0);
$guid = (int) get_input('file_guid');
$tags = get_input("tags");
$unzip = get_input('extract_zip', false);
$folder_guid = (int) get_input("folder_guid", 0);

if ($container_guid == 0) {
	$container_guid = elgg_get_logged_in_user_guid();
}

elgg_make_sticky_form('file');

// check to see if the user has exceeded the maximum file size
$max = (int) ini_get('upload_max_filesize');
if ((int) $_SERVER['CONTENT_LENGTH'] > $max) {
	register_error(elgg_echo("file:upload:max", [file_tools_get_readable_file_size_limit()]));
	forward(REFERER);
}

// check if upload attempted and failed
$files = _elgg_services()->request->files->get('upload');

/* @var UploadedFile $uploaded_file */
foreach ($files as $uploaded_file) {
	if (!$uploaded_file->isValid()) {
		$error = elgg_get_friendly_upload_error($uploaded_file->getError());
		register_error($error);
		forward(REFERER);
	}
}

// check whether this is a new file or an edit
$new_file = $guid <= 0;

$attrs = [
	'title' => htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
	'description' => htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'),
	'access_id' => $access_id,
	'container_guid' => $container_guid,
	'tags' => string_to_tag_array($tags)
];

if ($new_file) {
	$elgg_file = null;
} else {
	// can only edit one at a time, so look at the first file uploaded
	$files = [array_pop($files)];

	// load original file object
	/* @var ElggFile $elgg_file */
	$elgg_file = get_entity($guid);
	if (!$elgg_file instanceof ElggFile) {
		register_error(elgg_echo('file:cannotload'));
		forward(REFERER);
	}

	// user must be able to edit file
	if (!$elgg_file->canEdit()) {
		register_error(elgg_echo('file:noaccess'));
		forward(REFERER);
	}
}

// if passed a folder, verify it
// @todo check for canEdit() on folders?
$folder = null;
if ($folder_guid) {
	$folder = get_entity($folder_guid);

	if (!elgg_instanceof($folder, "object", FILE_TOOLS_SUBTYPE)) {
		register_error(elgg_echo('file_tools:upload:bad_folder'));
		forward(REFERER);
	}
}

// @todo This should batch notifications as well as river items
// disable notifications if uploading multiple files
//if (count($files) > 1) {
//	elgg_unregister_notification_event('object', 'file');
//}

/* @var UploadedFile $uploaded_file */
foreach ($files as $uploaded_file) {
	// check for zip files
	if ($unzip) {
		// @todo add support for unzipping
//		file_tools_unzip($uploaded_file, $container_guid, $folder_guid);
	}
	// only single file uploads use the title / desc fields
	if (count($files) > 1) {
		unset($attrs['description']);
	}

	if (!$attrs['title'] || count($files) > 1) {
		$attrs['title'] = htmlspecialchars($uploaded_file->getClientOriginalName(), ENT_QUOTES, 'UTF-8');
	}

	$file = file_tools_upload_to_elggfile($uploaded_file, $attrs, $elgg_file);

	if (!$file) {
		elgg_echo('file:uploadfailed');
		forward(REFERER);
	}

	// set folder
	// remove old relationships
	remove_entity_relationships($file->getGUID(), FILE_TOOLS_RELATIONSHIP, true);

	if ($folder_guid) {
		add_entity_relationship($folder_guid, FILE_TOOLS_RELATIONSHIP, $file->getGUID());
	}
}

// file saved so clear sticky form
elgg_clear_sticky_form('file');

if (count($files) == 1) {
	$message = elgg_echo("file:saved");
} else {
	$message = elgg_echo("file:saved:multiple");
}

system_message($message);

// handle results differently for new files and file updates
if ($new_file) {
	// @todo traditionally this created river entries for every file uploaded
	// probably want to only create one per batch of uploads
	elgg_create_river_item(array(
		'view' => 'river/object/file/create',
		'action_type' => 'create',
		'subject_guid' => elgg_get_logged_in_user_guid(),
		'object_guid' => $file->guid,
	));
} else {
	// editing a single file
	system_message(elgg_echo("file:saved"));
}

$container = get_entity($container_guid);

if (count($files) == 1) {
	forward($file->getURL());
} else if ($folder) {
	forward($folder->getURL());
} else if (elgg_instanceof($container, 'group')) {
	forward("file/group/$container->guid/all");
} else {
	forward("file/owner/$container->username");
}
