/**
 *
 */
define(function(require) {
	var elgg = require('elgg');
	var $ = require('jquery');

	/**
	 *
	 */
	var breadcrumb_click = function(event) {
		var href = $(this).attr("href");
		var hash = elgg.parse_url(href, "fragment");

		if (hash) {
			window.location.hash = hash;
		} else {
			window.location.hash = "#";
		}

		event.preventDefault();
	};

	/**
	 *
	 */
	var load_folder = function(folder_guid) {
		var query_parts = elgg.parse_url(window.location.href, "query", true);
		var search_type = 'list';

		if (query_parts && query_parts.search_viewtype) {
			search_type = query_parts.search_viewtype;
		}

		var url = elgg.get_site_url() + "file_tools/list/" + elgg.get_page_owner_guid()
			+ "?folder_guid=" + folder_guid + "&search_viewtype=" + search_type;

		$("#file_tools_list_files_container .elgg-ajax-loader").show();

		// don't expect json because only actions automatically return json.
		$.get(url)
			.done(function(data, status, xhr) {
				$("#file_tools_list_files_container").html(data);

				var new_add_link = elgg.get_site_url() + "file/add/" + elgg.get_page_owner_guid() + "?folder_guid=" + folder_guid;
				$('ul.elgg-menu-title li.elgg-menu-item-add a').attr("href", new_add_link);

				var new_zip_link = elgg.get_site_url() + "file/zip/" + elgg.get_page_owner_guid() + "?folder_guid=" + folder_guid;
				$('ul.elgg-menu-title li.elgg-menu-item-zip-upload a').attr("href", new_zip_link);

				initialize_file_draggable();
				initialize_folder_droppable();
			});
	};

	/**
	 *
	 */
	var move_file = function(file_guid, to_folder_guid, draggable) {
		elgg.action("file/move", {
			data: {
				file_guid: file_guid,
				folder_guid: to_folder_guid
			},
			error: function(result) {
				var hash = elgg.parse_url(window.location.href, "fragment");

				if (hash) {
					load_folder(hash);
				} else {
					load_folder(0);
				}
			},
			success: function(result) {
				draggable.remove();
			}
		});
	};

	/**
	 *
	 */
	var select_all = function(e) {
		e.preventDefault();

		if ($(this).find("span:first").is(":visible")) {
			// select all
			$('#file_tools_list_files input[type="checkbox"]').prop("checked", true);
		} else {
			// deselect all
			$('#file_tools_list_files input[type="checkbox"]').prop("checked", false);
		}

		$(this).find("span").toggle();
	};

	/**
	 *
	 */
	var bulk_delete = function(e) {
		e.preventDefault();

		$checkboxes = $('#file_tools_list_files input[type="checkbox"]:checked');

		if (!$checkboxes.length) {
			return;
		}

		if (!confirm(elgg.echo("deleteconfirm"))) {
			return;
		}

		var postData = $checkboxes.serializeJSON();

		if ($('#file_tools_list_files input[type="checkbox"][name="folder_guids[]"]:checked').length && confirm(elgg.echo("file_tools:folder:delete:confirm_files"))) {
			postData.files = "yes";
		}

		$("#file_tools_list_files_container .elgg-ajax-loader").show();

		elgg.action("file/bulk_delete", {
			data: postData,
			success: function(res){
				$.each($checkboxes, function(key, value) {
					$('#elgg-object-' + $(value).val()).remove();
				});

				$("#file_tools_list_files_container .elgg-ajax-loader").hide();
			}
		});
	};

	/**
	 *
	 */
	var bulk_download = function(e) {
		e.preventDefault();

		$checkboxes = $('#file_tools_list_files input[type="checkbox"]:checked');

		if ($checkboxes.length) {
			elgg.forward("file/bulk_download?" + $checkboxes.serialize());
		}
	};

	/**
	 *
	 */
	var show_more_files = function() {
		$(this).hide();
		$('#file_tools_list_files div.elgg-ajax-loader').show();

		var offset = $(this).siblings('input[name="offset"]').val();
		var folder = $(this).siblings('input[name="folder_guid"]').val();
		var query_parts = elgg.parse_url(window.location.href, "query", true);
		var search_type = 'list';

		if (query_parts && query_parts.search_viewtype) {
			search_type = query_parts.search_viewtype;
		}

		elgg.post("file_tools/list/" + elgg.get_page_owner_guid(), {
			data: {
				folder_guid: folder,
				search_viewtype: search_type,
				offset: offset
			},
			success: function(data) {
				// append the files to the list
				var li = $(data).find("ul.elgg-list-entity > li");
				$('#file_tools_list_files ul.elgg-list').append(li);
				initialize_file_draggable();
				initialize_folder_droppable()

				// replace the show more button with new data
				var show_more = $(data).find("#file-tools-show-more-wrapper");
				$("#file-tools-show-more-wrapper").replaceWith(show_more);

				// hide ajax loader
				$('#file_tools_list_files div.elgg-ajax-loader').hide();
			}
		});

	};

	/**
	 *
	 */
	var initialize_file_draggable = function() {
		$("#file_tools_list_files .elgg-item-object-file").draggable({
			revert: "invalid",
			opacity: 0.8,
			appendTo: "body",
			helper: "clone",
			start: function(event, ui) {
				$(this).css("visibility", "hidden");
				$(ui.helper).width($(this).width());
			},
			stop: function(event, ui) {
				$(this).css("visibility", "visible");
			}
		});
	};

	/**
	 *
	 */
	var initialize_folder_droppable = function() {
		$("#file_tools_list_files .elgg-item-object-folder").droppable({
			accept: "#file_tools_list_files .elgg-item-object-file",
			drop: function(event, ui){
				var droppable = $(this);
				var draggable = ui.draggable;

				var drop_id = droppable.prop("id").split("-").pop();
				var drag_id = draggable.prop("id").split("-").pop();

				move_file(drag_id, drop_id, draggable);
			}
		});
	};

	var handleFileUploadChange = function(e) {
		var files = this.files;


		// if more than one file, hide the title and desc fields
		if (files.length > 1) {
			$('.file-tools-title-and-desc').hide('fast');
		} else {
			$('.file-tools-title-and-desc').show('fast');
		}

		// var showZip = false;
		//
		// for (var i = 0; i < files.length; i++) {
		// 	var ext = files[i].name.split('.').pop();
		// 	// if any files are zips, offer to expand them
		// 	if (ext.toLowerCase() == 'zip') {
		// 		showZip = true;
		// 		break;
		// 	}
		// }
		//
		// if (showZip) {
		// 	$('.file-tools-extract-zips').show('fast');
		// } else {
		// 	$('.file-tools-extract-zips').hide('fast');
		// }
	};

	$('form').on('change', '#file-tools-upload', handleFileUploadChange);
	$(document).on("click", '#file_tools_breadcrumbs a', breadcrumb_click);
	$(document).on("click", '#file_tools_select_all', select_all);
	$(document).on("click", '#file_tools_action_bulk_delete', bulk_delete);
	$(document).on("click", '#file_tools_action_bulk_download', bulk_download);
	$(document).on("click", '#file-tools-show-more-files', show_more_files);

	return {
		'load_folder': load_folder,
		'move_file': move_file
	};
});
