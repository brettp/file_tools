<?php
/**
 * All event handlers are bundled here
 */

/**
 * Listen to delete event on objects
 *
 * @param string     $event  the name of the event
 * @param string     $type   the type of the event
 * @param ElggObject $object supplied object
 *
 * @return void
 */
function file_tools_object_handler_delete($event, $type, $object) {
	static $delete_files;
	
	if (!empty($object) && elgg_instanceof($object, "object", FILE_TOOLS_SUBTYPE)) {
		// find subfolders
		$options = array(
			"type" => "object",
			"subtype" => FILE_TOOLS_SUBTYPE,
			"container_guid" => $object->getContainerGUID(),
			"limit" => false,
			"metadata_name_value_pairs" => array(
				"name" => "parent_guid",
				"value" => $object->getGUID()
			),
			"wheres" => array("(e.guid <> " . $object->getGUID() . ")") // prevent deadloops
		);

		if ($subfolders = elgg_get_entities_from_metadata($options)) {
			// delete subfolders
			foreach ($subfolders as $subfolder) {
				$subfolder->delete();
			}
		}

		// fill the static, to delete files in a folder
		if (!isset($delete_files)) {
			$delete_files = false;
			
			if (get_input("files") == "yes") {
				$delete_files = true;
			}
		}
		
		// should we remove files?
		if ($delete_files) {
			// find file in this folder
			$options = array(
				"type" => "object",
				"subtype" => "file",
				"container_guid" => $object->getContainerGUID(),
				"limit" => false,
				"relationship" => FILE_TOOLS_RELATIONSHIP,
				"relationship_guid" => $object->getGUID()
			);
				
			if ($files = elgg_get_entities_from_relationship($options)) {
				// delete files in folder
				foreach ($files as $file) {
					$file->delete();
				}
			}
		}
	}
}
