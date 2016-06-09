<?php

$guid = (int) get_input("guid");
$title = get_input("title");
$container_guid = (int) get_input("container_guid", elgg_get_page_owner_guid());
$description = get_input("description");
$parent_guid = (int) get_input("file_tools_parent_guid", 0); // 0 is top_level
$access_id = (int) get_input("access_id", ACCESS_DEFAULT);
$change_children_access = get_input("change_children_access", false);
$change_files_access = get_input("change_files_access", false);

if (!$title || !$container_guid) {
	register_error(elgg_echo("file_tools:action:edit:error:input"));
	forward(REFERRER);
}

// verify only users or groups can be containers
$container = get_entity($container_guid);
if (!elgg_instanceof($container, "user") && !elgg_instanceof($container, "group")) {
	register_error(elgg_echo("file_tools:action:edit:error:owner"));
}

// edit or create a new folder
if ($guid) {
	$folder = get_entity($guid);
	if (!elgg_instanceof($folder, "object", FILE_TOOLS_SUBTYPE)) {
		register_error(elgg_echo("file_tools:action:edit:error:folder"));
		forward(REFERRER);
	}
} else {
	$folder = new ElggObject();
	$folder->subtype = FILE_TOOLS_SUBTYPE;
	$folder->container_guid = $container_guid;

	$order = elgg_get_entities_from_metadata(array(
		"type" => "object",
		"subtype" => FILE_TOOLS_SUBTYPE,
		"metadata_name_value_pairs" => array(
			"name" => "parent_guid",
			"value" => $parent_guid
		),
		"count" => true
	));

	$folder->order = $order;

}

// don't let a folder be assigned as its own parent
if ($folder->getGUID() && ($folder->getGUID() == $parent_guid)) {
	register_error(elgg_echo("file_tools:action:edit:error:parent_guid"));
	forward(REFERRER);
}

$folder->title = $title;
$folder->description = $description;
$folder->access_id = $access_id;
$folder->parent_guid = $parent_guid;

if (!$folder->save()) {
	register_error(elgg_echo("file_tools:action:edit:error:save"));
	forward(REFERRER);
}

if ($change_children_access) {
	file_tools_change_children_access($folder, $change_files_access);
} else if ($change_files_access) {
	file_tools_change_files_access($folder);
}

system_message(elgg_echo("file_tools:action:edit:success"));

forward($folder->getURL());
