<?php
class vendor_reply_class{
		if($post_datatable){
			$count = 0;
			foreach($post_datatable as $post_datatable_key => $post_datatable_value){
				if($post_datatable_value['comment_id'] == $cat_id){
					$count++;
				}
			}
			return $count;
		}
	}
}