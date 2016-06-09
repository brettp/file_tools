<?php

$num = $vars['entity']->num_display;

$widget = elgg_extract("entity", $vars);
$site_url = elgg_get_site_url();

$options = array(
	'type' => 'object',
	'subtype' => 'file',
	'container_guid' => $vars['entity']->owner_guid,
	'limit' => $num,
	'full_view' => FALSE,
	'pagination' => FALSE,
	'distinct' => false,
);

// show only featured files
if ($widget->featured_only == "yes") {
	$options["metadata_name_value_pairs"] = array(
		"name" => "show_in_widget",
		"value" => "0",
		"operand" => ">"
	);
}

if ($widget->gallery_list == 2) {
	elgg_push_context('gallery');
}

$content = elgg_list_entities_from_metadata($options);

echo $content;

if ($content) {
	$url = "file/owner/" . elgg_get_page_owner_entity()->username;
	$more_link = elgg_view('output/url', array(
		'href' => $url,
		'text' => elgg_echo('file:more'),
		'is_trusted' => true,
	));
	echo "<span class=\"elgg-widget-more\">$more_link</span>";
} else {
	echo elgg_echo('file:none');
}

if ($widget->gallery_list == 2) {
	elgg_pop_context();
}