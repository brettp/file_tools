<?php

$folders = elgg_extract("folders", $vars);
$folder = elgg_extract("folder", $vars);

$selected_id = "file_tools_list_tree_main";
if ($folder instanceof ElggObject) {
	$selected_id = $folder->getGUID();
}

$page_owner = elgg_get_page_owner_entity();
$site_url = elgg_get_site_url();

// load JS
elgg_load_css("jquery.tree");
elgg_require_js('file_tools/tree');

$body = "<div id='file-tools-folder-tree' class='clearfix hidden'>";
$body .= elgg_view_menu("file_tools_folder_sidebar_tree", array(
	"container" => $page_owner,
	"sort_by" => "priority"
));
$body .= "</div>";

$user_guid = elgg_get_logged_in_user_guid();

if ($page_owner->canWriteToContainer($user_guid, 'object', FILE_TOOLS_SUBTYPE) &&
	$page_owner->file_tools_structure_management_enable != "no"
) {
	elgg_load_js("lightbox");
	elgg_load_css("lightbox");

	$body .= "<div class='mtm'>";
	$body .= elgg_view("output/url", array(
		"text" => elgg_echo("file_tools:new:title"),
		"href" => "file_tools/folder/new/" . elgg_get_page_owner_guid() . "?parent_guid=" . get_input('folder_guid', 0),
		'data-url-format' => "file_tools/folder/new/{owner_guid}?folder_guid={folder_guid}",
		"class" => "elgg-button elgg-button-action elgg-lightbox"
	));
	$body .= "</div>";
}

// output file tree
echo elgg_view_module("aside", "", $body, array("id" => "file_tools_list_tree_container"));
