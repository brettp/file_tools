<?php
/**
 * Override the upload form to inject the folder selection input
 */
elgg_require_js('file_tools/site');

$title = elgg_extract('title', $vars, '');
$desc = elgg_extract('description', $vars, '');
$tags = elgg_extract('tags', $vars, '');
$access_id = elgg_extract('access_id', $vars, ACCESS_DEFAULT);
$container_guid = elgg_extract('container_guid', $vars);
if (!$container_guid) {
	$container_guid = elgg_get_logged_in_user_guid();
}
$guid = elgg_extract('guid', $vars, null);

$file_input_options = [
	'type' => 'file',
	'name' => 'upload[]',
	'id' => 'file-tools-upload',
	'label' => $file_label,
	'help' => $upload_limit . '<br />' . $multi_msg,
	'value' => ($guid),
	'required' => (!$guid)
];

if ($guid) {
	$file_label = elgg_echo("file:replace");
	$submit_label = elgg_echo('save');
} else {
	$file_label = elgg_echo("file:file");
	$submit_label = elgg_echo('upload');
	$file_input_options['multiple'] = 'multiple';
}

// Get post_max_size and upload_max_filesize
$post_max_size = elgg_get_ini_setting_in_bytes('post_max_size');
$upload_max_filesize = elgg_get_ini_setting_in_bytes('upload_max_filesize');

// Determine the correct value
$max_upload = $upload_max_filesize > $post_max_size ? $post_max_size : $upload_max_filesize;

$upload_limit = elgg_echo('file:upload_limit', array(elgg_format_bytes($max_upload)));
$multi_msg = elgg_echo('file_tools:upload:hold_ctrl_for_multiple');

$fields = [
	$file_input_options,
	[
		'val' =>
			'<div class="elgg-field hidden file-tools-extract-zips">'
		.
			elgg_view('input/checkbox', [
			'name' => 'extract_zip',
			'value' => false,
			'label' => elgg_echo("file_tools:extract_zip")
		])
		. '<div class="elgg-field-help elgg-text-help">' . elgg_echo("file_tools:upload:extract_zips") . '</div></div>'
	],
];

$title_input = elgg_view_input('text', [
	'name' => 'title',
	'value' => $title,
	'id' => 'file-tools-title',
	'label' => elgg_echo('title'),
]);

$desc_input = elgg_view_input('longtext', [
	'name' => 'description',
	'id' => 'file-tools-description',
	'value' => $desc,
	'label' => elgg_echo('description'),
]);

$tags_input = elgg_view_input('tags', [
	'name' => 'tags',
	'value' => $tags,
	'label' => elgg_echo('tags'),
]);

$cat_input = elgg_view_input('categories', $vars);

$fields[] = [
	'type' => 'title_and_desc',
	'val' => "<div class='file-tools-title-and-desc'>$title_input $desc_input $tags_input $cat_input</div>"
];

if (file_tools_use_folder_structure()) {
	$parent_guid = 0;
	$file = elgg_extract("entity", $vars);
	if ($file) {
		if ($folders = $file->getEntitiesFromRelationship(array(
			'relationship' => FILE_TOOLS_RELATIONSHIP,
			'inverse_relationship' => true,
			'limit' => 1
		))
		) {
			$parent_guid = $folders[0]->getGUID();
		}
	}
	$fields[] = [
		'type' => 'folder_select',
		'name' => 'folder_guid',
		'label' => elgg_echo("file_tools:forms:edit:parent"),
		'value' => $parent_guid
	];
}

$fields = array_merge($fields, [
	[
		'type' => 'access',
		'name' => 'access_id',
		'value' => $access_id,
		'entity' => get_entity($guid),
		'entity_type' => 'object',
		'entity_subtype' => 'file',
		'label' => elgg_echo('access'),
	],
	[
		'type' => 'hidden',
		'name' => 'container_guid',
		'value' => $container_guid,
	],
	[
		'type' => 'hidden',
		'name' => 'file_guid',
		'value' => $guid,
	],
	[
		'type' => 'submit',
		'value' => $submit_label,
		'field_class' => 'elgg-foot',
	]
]);

foreach ($fields as $field) {
	$type = elgg_extract('type', $field, 'text');
	unset($field['type']);
	if ($type == 'categories') {
		$field = $vars;
	} else if (isset($field['val'])) {
		echo $field['val'];
		continue;
	}

	echo elgg_view_input($type, $field);
}
