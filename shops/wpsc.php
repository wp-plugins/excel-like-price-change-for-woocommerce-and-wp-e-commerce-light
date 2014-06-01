<?php
/*
Title: WP E-Commerce
Origin plugin: wp-e-commerce/wp-shopping-cart.php
*/
?>
<?php

if ( !function_exists('add_action') ) {
	header('Status: 403 Forbidden');
	header('HTTP/1.1 403 Forbidden');
	exit();
}

$variations_skip = array( 'categories','status');

ob_clean();

if(isset($_REQUEST['keep_alive'])){
	if($_REQUEST['keep_alive']){
		return;
	}
}

global $wpdb;
global $wpsc_fields_visible;
global $custom_fileds, $use_image_picker, $use_content_editior;


$use_image_picker    = false;
$use_content_editior = false;

$wpsc_fields_visible = array();
if(isset($plem_settings['wpsc_fileds'])){
	foreach(explode(",",$plem_settings['wpsc_fileds']) as $I => $val){
		if($val)
			$wpsc_fields_visible[$val] = true;
	}
}

function fn_show_filed($name){
	global $wpsc_fields_visible;
	
	if(empty($wpsc_fields_visible))
		return $name != "image";
		
	if(isset($wpsc_fields_visible[$name]))
		return $wpsc_fields_visible[$name];
	
	$wpsc_fields_visible[$name] = false;
	return false;
};

function fn_correct_type($s){
  if( is_numeric($s))
	return intval($s);
  else 
    return trim($s);  
};

function fn_set_meta_by_path(&$id,$path,$value){
    $ind_key  = strpos($path, '!');
	$ind_prop = strpos($path, '.');
    if($ind_key !== false || $ind_prop !== false){
        $object   = null;
		$is_object  = false;
		$meta_key = "";
		$parent_is_object = false;
		if($ind_key > $ind_prop && $ind_prop !== false){
			$meta_key = substr( $path , 0 , $ind_prop);
			$object   = get_post_meta($id,$meta_key,true);
			$path     = substr($path,$ind_prop + 1);
			if(!$object)
				$object = new stdClass();
			$parent_is_object = !is_array($object);	
		}else{
			$meta_key = substr( $path , 0 , $ind_key);
		    $object   = get_post_meta($id,$meta_key,true);
			$path     = substr($path,$ind_key + 1);
			if(!$object)
				$object = array();
			$parent_is_object = !is_array($object);		
		}
		$ptr = &$object;
		do{
		   $is_object = false;
		   $ind_key  = strpos($path, '!');
		   $ind_prop = strpos($path, '.');
    	   $ind = -1; 
		   if($ind_key !== false){
		     if($ind_prop !== false){
			    $ind = $ind_key > $ind_prop? $ind_prop : $ind_key;
				if($ind === $ind_prop)
					$is_object = true;
			 }else
			    $ind = $ind_key;
		   }elseif($ind_prop !== false){
				$ind = $ind_prop;
				$is_object = true;
		   }		   
		   if($ind != -1){ 
			   $key  =  substr( $path , 0 , $ind);
			   if($key === '' || $key === null){
			     $path = $key;
				 break;
			   }
			   $path =  substr( $path , $ind + 1);
		   }else 
		       break;
		   if($parent_is_object){
		        if(!isset($ptr->{$key}))
					$ptr->{$key} = $is_object ? new stdClass() : array();
				$ptr = &$ptr->{$key};  
		   }else{
				if(!isset($ptr[$key]))
					$ptr[$key] = $is_object ? new stdClass() : array();		   
				$ptr = &$ptr[$key];
           }	   
		   $parent_is_object = !is_array($ptr);
		}while($ind_key !== false || $ind_prop !== false);
        if($is_object)
			$ptr->{$path} = $value;
        else
			$ptr[$path] = $value;
	    update_post_meta($id, $meta_key, $object);
	}else 
		update_post_meta($id, $path, $value);
};

function fn_get_meta_by_path($id,$path){
	if(strpos($path, '!') !== false || strpos($path, '.') !== false){
		$object   = null;
		$is_object  = false;
		$meta_key = "";
		$parent_is_object = false;
		
		if($ind_key > $ind_prop && $ind_prop !== false){
			$meta_key = substr( $path , 0 , $ind_prop);
			$object   = get_post_meta($id,$meta_key,true);
			$path     = substr($path,$ind_prop + 1);
			if(!$object)
				return null;
			$parent_is_object = !is_array($object);	
		}else{
			$meta_key = substr( $path , 0 , $ind_key);
		    $object   = get_post_meta($id,$meta_key,true);
			$path     = substr($path,$ind_key + 1);
			if(!$object)
				return null;
			$parent_is_object = !is_array($object);		
		}
		$ptr = &$object;
		do{
		   $is_object = false;
		   $ind_key  = strpos($path, '!');
		   $ind_prop = strpos($path, '.');
    	   $ind = -1; 
		   if($ind_key !== false){
		     if($ind_prop !== false){
			    $ind = $ind_key > $ind_prop? $ind_prop : $ind_key;
				if($ind === $ind_prop)
					$is_object = true;
			 }else
			    $ind = $ind_key;
		   }elseif($ind_prop !== false){
				$ind = $ind_prop;
				$is_object = true;
		   }	
		   
		   if($ind != -1){ 
			   $key  =  substr( $path , 0 , $ind);
			   if($key === '' || $key === null){
			     $path = $key;
				 break;
			   }
			   $path =  substr( $path , $ind + 1);
		   }else 
		       break;
			   
		   if($parent_is_object){
		        if(!isset($ptr->{$key}))
					return null;
				$ptr = &$ptr->{$key};  
		   }else{
				if(!isset($ptr[$key]))
					return null;
				$ptr = &$ptr[$key];
           }	   
		   $parent_is_object = !is_array($ptr);
		}while($ind_key !== false || $ind_prop !== false);
        
		if($is_object)
			return $ptr->{$path};
        else
			return $ptr[$path];
	}else
		return get_post_meta($id,$path,true);
};


function fn_convert_unit($value,$from,$to){
	if($from == "pound"){
	  if($to == "ounce") $value *= 16;
	  elseif($to == "gram") $value *= 453.59237;
	  elseif($to == "kilogram") $value *= 0.45359237;
	}elseif($from == "ounce"){
	  if($to == "pound") $value *= 0.0625; 
	  elseif($to == "gram") $value *= 28.3495231;
	  elseif($to == "kilogram") $value *= 0.0283495231;
	}elseif($from == "gram"){
	  if($to == "pound") $value *= 0.00220462262;
	  elseif($to == "ounce") $value *= 0.0352739619;
	  elseif($to == "kilogram") $value *= 0.001;
	}elseif($from == "kilogram"){
	  if($to == "pound") $value *= 2.204622;
	  elseif($to == "ounce") $value *= 35.2739619;
	  elseif($to == "gram") $value *= 1000;
	}elseif($from == "in"){
	  if($to == "cm") $value *= 2.54;
	  elseif($to == "meter") $value *= 0.0254; 
	}elseif($from == "cm"){ 
	  if($to == "in") $value *= 0.393700787;
	  elseif($to == "meter") $value *= 0.01;
	}elseif($from == "meter"){
      if($to == "in") $value *= 39.3700787;
	  elseif($to == "cm") $value *= 100;
	}  
	return $value;
};

function get_array_value(&$array,$key,$default){
   if(isset($array[$key]))
	  return $array[$key];
   else
	  return $default;
}; 

$custom_fileds = array();
function loadCustomFields(&$plem_settings,&$custom_fileds){
    global $use_image_picker, $use_content_editior;
	
	
	for($I = 0 ; $I < 8 ; $I++){
		$n = $I + 1;
		if(isset($plem_settings["wpsccf_enabled".$n])){
			if($plem_settings["wpsccf_enabled".$n]){
				$cfield = new stdClass();
				
				$cfield->type  = get_array_value($plem_settings,"wpsccf_type".$n, "");
				if(!$cfield->type)
				  continue;
				  
				$cfield->title = get_array_value($plem_settings,"wpsccf_title".$n, "");
				if(!$cfield->title)
				  continue;
			   
				$cfield->source = get_array_value($plem_settings,"wpsccf_source".$n, "");
				if(!$cfield->source)
				  continue;  
				  
				$cfield->options = get_array_value($plem_settings,"wpsccf_editoptions".$n, "");
				if(!$cfield->options)
				  continue;

				$cfield->options = json_decode($cfield->options);
				if(!$cfield->options)
					continue;
					
				if($cfield->type == 'term'){
				   $cfield->terms = array();
				   $terms = get_terms( $cfield->source , array('hide_empty' => false));
				   foreach($terms as $val){
						$value            = new stdClass();
						$value->value     = $val->term_id;
						//$value->slug      = $val->slug;
						$value->name      = $val->name;
						//$value->parent    = $val->parent;
						$cfield->terms[]  = $value;
					}
				}else{
					if($cfield->options->formater == "content")
						$use_content_editior = true;
					elseif($cfield->options->formater == "image")
						$use_image_picker    = true;
				}	
					
				$cfield->name = 'cf_'. strtolower($cfield->source);			
				$custom_fileds[$cfield->name] = $cfield;	
			}   
		}
	}
};

loadCustomFields($plem_settings,$custom_fileds);


$limit = 1000;
if(isset($_COOKIE['pelm_txtlimit']))
	$limit = $_COOKIE['pelm_txtlimit'] ? $_COOKIE['pelm_txtlimit'] : 1000;

	
	
$page_no  = 1;

$orderby         = "ID";
$orderby_key     = "";

$sort_order  = "DESC";
$sku = '';
$product_name = '';
$product_category = '';
$product_tag      = '';
$product_status   = '';

if(isset($_REQUEST['limit'])){
	$limit = $_REQUEST['limit'];
}

if(isset($_REQUEST['page_no'])){
	$page_no = $_REQUEST['page_no'];
}

if(isset($_REQUEST['sku'])){
	$sku = $_REQUEST['sku'];
}

if(isset($_REQUEST['product_name'])){
	$product_name = $_REQUEST['product_name'];
}

if(isset($_REQUEST['product_category'])){
	$product_category = explode(",", $_REQUEST['product_category']);
}

if(isset($_REQUEST['product_tag'])){
	$product_tag = explode(",", $_REQUEST['product_tag']);
}


if(isset($_REQUEST['product_status'])){
	$product_status = explode(",", $_REQUEST['product_status']);
}	

if(isset($_REQUEST['sortColumn'])){
	$orderby = $_REQUEST['sortColumn'];
	
	if($orderby == "id") $orderby = "ID";
	elseif($orderby == "sku") {
	    $orderby = "meta_value";
		$orderby_key = "_wpsc_sku";
	}
	elseif($orderby == "slug") $orderby = "name";
	elseif($orderby == "categories") {
		$orderby = "category_name";
		//???? this is not correct
	}
	elseif($orderby == "name") $orderby = "title";
    elseif($orderby == "stock") {
		$orderby = "meta_value_num";
		$orderby_key = "_wpsc_stock";
	}
	elseif($orderby == "price") {
		$orderby = "meta_value_num";
		$orderby_key = "_wpsc_price";
	}
	elseif($orderby == "override_price") {
		$orderby = "meta_value_num";
		$orderby_key = "_wpsc_special_price";
	}
	elseif($orderby == "status"){ 
		$orderby = "status";
	}
	elseif($orderby == "tags"){ 
		$orderby = "tag";
		//???? this is not correct
	}
}

if(isset($_REQUEST['sortOrder'])){
	$sort_order = $_REQUEST['sortOrder'];
}

if(isset($_REQUEST['DO_UPDATE'])){
if($_REQUEST['DO_UPDATE'] == '1' && strtoupper($_SERVER['REQUEST_METHOD']) == 'POST'){
    
	$json = file_get_contents('php://input');
	$tasks = json_decode($json);
	
	$res = array();
	$temp = '';
	
	foreach($tasks as $key => $task){
	   $res_item = new stdClass();
	   $res_item->id = $key;
	   $upd_prop = array();
	  
       $post_update = array( 'ID' => $key );
	  
	   if(isset($task->price)){ 
		  update_post_meta($key, '_wpsc_price', $task->price);
	   }
	   
	   if(isset($task->override_price)){
	      update_post_meta($key, '_wpsc_special_price', $task->override_price);
	   }
	   
	   $res_item->success = true;
	   $res[] = $res_item;
	}
	
	echo json_encode($res);
    exit; 
	return;
}
}


$import_count = 0;
if(isset($_REQUEST["do_import"])){
	if($_REQUEST["do_import"] = "1"){
	    $n = 0;
		if (($handle = fopen($_FILES['file']['tmp_name'], "r")) !== FALSE) {
			$id_index                = -1;
			$price_index             = -1;
			$price_o_index           = -1;
			$stock_index             = -1;
			$sku_index               = -1;
			$name_index              = -1;
            $slug_index              = -1;
			$status_index            = -1;
			$categories_names_index  = -1;
			$tags_names_index        = -1;
			$weight_index            = -1;
			$height_index            = -1;
			$width_index             = -1;
			$length_index            = -1;
			$taxable_index           = -1;
			$loc_shipping_index      = -1;
			$int_shipping_index      = -1;
			$cf_indexes                = array();

			
			while (($data = fgetcsv($handle, 8192, ",")) !== FALSE) {
				if($n == 0){
				   	$id_index    = 0;
					for($i = 0 ; $i < count($data); $i++){
				        if($data[$i]     == "id") $id_index = $i;
						elseif($data[$i] == "price") $price_index = $i;
						elseif($data[$i] == "override_price") $price_o_index = $i;
						elseif($data[$i] == "sku") $sku_index   = $i;
						elseif($data[$i] == 'stock') $stock_index = $i;
						elseif($data[$i] == 'name') $name_index = $i;
						elseif($data[$i] == 'slug') $slug_index = $i;
						elseif($data[$i] == 'status') $status_index = $i;
						elseif($data[$i] == 'categories_names') $categories_names_index = $i;
						elseif($data[$i] == 'tags_names') $tags_names_index = $i;
						elseif($data[$i] == 'weight') $weight_index            = $i;
						elseif($data[$i] == 'height') $height_index            = $i;
						elseif($data[$i] == 'width')  $width_index             = $i;
						elseif($data[$i] == 'length') $length_index            = $i;
						elseif($data[$i] == 'taxable') $taxable_index           = $i;
						elseif($data[$i] == 'loc_shipping') $loc_shipping_index      = $i;
						elseif($data[$i] == 'int_shipping') $int_shipping_index      = $i;
						
						foreach($custom_fileds as $cfname => $cfield){
							if($cfname == $data[$i]){
								$cf_indexes[$cfname] = $i;
								break;
							}
						}

					}
				}else{
				   $id = $data[$id_index];
				   if(!$id)
					continue;
						
				   $post_update = array( 'ID' => $id );
	  
				   if($sku_index > 0){ 
					  update_post_meta($id, '_wpsc_sku', $data[$sku_index]);
				   }
				   
				   if($stock_index > 0){ 
					  update_post_meta($id, '_wpsc_stock', $data[$stock_index]);
				   }
				   
				   if($price_index > 0){ 
					  update_post_meta($id, '_wpsc_price', $data[$price_index]);
				   }
				   
				   if($price_o_index > 0){
					  update_post_meta($id, '_wpsc_special_price', $data[$price_o_index]);
				   }
				   
				   if($status_index > 0){
					  $post_update['post_status'] = $data[$status_index];
				   }
				   
				   if($name_index > 0){ 
					  $post_update['post_title'] = $data[$name_index];  
				   }
				   
				   if($slug_index > 0){ 
					  $post_update['post_name'] = urlencode($data[$slug_index]);  
				   }
				   
				   if(count($post_update) > 1){
					  wp_update_post($post_update);;
				   }
				   
				   $pr_meta = get_post_meta($id,'_wpsc_product_metadata',true);
				   $dimensions = &$pr_meta['dimensions'];
				   if(!$dimensions){
					  $pr_meta['dimensions'] = array();
					  $dimensions = &$pr_meta['dimensions'];
				   }
				   
				   if($weight_index > 0){
					   $weight = $data[$weight_index];  
					   if($weight){
						   $pr_meta['weight']       = floatval($weight);
						   $pr_meta['weight_unit']  = str_replace(" ","",str_replace($pr_meta['weight'],'',$weight));
						   $pr_meta['weight'] = fn_convert_unit($pr_meta['weight'],$pr_meta['weight_unit'],'pound');
					   }else{
						   $pr_meta['weight']       = '';   
						   $pr_meta['weight_unit']  = '';
					   }
				   }
				   
				   if($height_index > 0){
				   	   $height = $data[$height_index];  
					   if($height){
						   $dimensions['height']       = floatval($height);
						   $dimensions['height_unit']  = str_replace(" ","",str_replace($dimensions['height'],'',$height));
					   }else{
						   $dimensions['height']       = ''; 
						   $dimensions['height_unit']  = '';
					   }
				   }
				   
				   if($width_index > 0){
					   $width = $data[$width_index];  
					   if($height){
						   $dimensions['width']        = floatval($width);
						   $dimensions['width_unit']   = str_replace(" ","",str_replace($dimensions['width'],'',$width));
					   }else{
						   $dimensions['width']        = '';
						   $dimensions['width_unit']   = '';
					   }				   
				   }
				   
				   if($length_index > 0){
					   $length = $data[$length_index];  
					   if($height){
						   $dimensions['length']       = floatval($length);
						   $dimensions['length_unit']  = str_replace(" ","",str_replace($dimensions['length'],'',$length));
					   }else{
						   $dimensions['length']       = '';
						   $dimensions['length_unit']  = '';
					   }
				   }
				   
				   if($taxable_index > 0){
				   	   $pr_meta['wpec_taxes_taxable_amount'] = $data[$taxable_index];  
				   }
				   
				   if($loc_shipping_index > 0){
					   $pr_meta['shipping']['local']         = $data[$loc_shipping_index];  
				   }
				   
				   if($int_shipping_index > 0){
				   	   $pr_meta['shipping']['international'] = $data[$int_shipping_index];  
				   }
				   
				   update_post_meta($id, '_wpsc_product_metadata', $pr_meta);

				   if($categories_names_index > 0){
					  wp_set_object_terms( $id ,  explode(",",$data[$categories_names_index]) , 'wpsc_product_category' );
				   }
				   
				   if($tags_names_index > 0){
					  wp_set_object_terms( $id , explode(",",$data[$tags_names_index]) , 'product_tag' );
				   }
				   
				   foreach($custom_fileds as $cfname => $cfield){ 
						if(isset($cf_indexes[$cfname])){
						   if($cfield->type == "term"){
								wp_set_object_terms( $id , array_map(fn_correct_type, explode(",",$data[$cf_indexes[$cfname]])) , $cfield->source );
						   }elseif($cfield->type == "meta"){
								fn_set_meta_by_path( $id , $cfield->source, $data[$cf_indexes[$cfname]]);  
						   }elseif($cfield->type == "post"){
						        $wpdb->query( 
									$wpdb->prepare( "UPDATE $wpdb->posts SET ".$cfield->source." = %s WHERE ID = %d", $data[$cf_indexes[$cfname]] ,$id )
								); 
						   }
						}
				   }

				   
				   $import_count ++;
				}
				$n++;			
			}
			fclose($handle);
		}
		
		$custom_fileds   = array();
		loadCustomFields($plem_settings,$custom_fileds);
	}
}

global $categories, $cat_asoc;
$categories = array();
$cat_asoc   = array();

function list_categories_callback($category, $level, $parameters){
   global $categories, $cat_asoc;
   $cat = new stdClass();
   $cat->category_id     = $category->term_id;
   $cat->category_name   = $category->name;
   $cat->category_slug   = urldecode($category->slug);
   $cat->category_parent = $category->parent;
   $categories[] = $cat;   
   $cat_asoc[$cat->category_id] = $cat;
};

$res = wpsc_list_categories('list_categories_callback');


$_num_sample = (1/2).'';
$args = array(
	 'post_type' => array('wpsc-product','wpsc-variation')
	,'posts_per_page' => -1
	,'ignore_sticky_posts' => false
	,'orderby' => $orderby 
	,'order' => $sort_order
	,'fields' => 'ids'
);

if($product_status)
	$args['post_status'] = $product_status;
else
	$args['post_status'] = 'any';

if($orderby_key)
   $args['meta_key'] = $orderby_key;

$meta_query = array();

if(isset($product_name)){
	global $wpdb;
    $name_postids = $wpdb->get_col("select ID from $wpdb->posts where post_title like '%$product_name%' ");
    $args['post__in'] = empty($name_postids) ? array(-9999) : $name_postids;
}

$tax_query = array();

if($product_category){
 	$tax_query[] =  array(
						'taxonomy' => 'wpsc_product_category',
						'field' => 'id',
						'terms' => $product_category
					);
}



if($product_tag){
	$tax_query[] =  array(
						'taxonomy' => 'product_tag',
						'field' => 'id',
						'terms' => $product_tag
					);
}

if($sku){
	$meta_query[] =	array(
						'key' => '_wpsc_sku',
						'value' => $sku,
						'compare' => 'LIKE'
					);
}

if(!empty($tax_query )){
	$args['tax_query']  = $tax_query;
}

if(!empty($meta_query))
	$args['meta_query'] = $meta_query;


$tags           = array();
foreach((array)get_terms('product_tag',array('hide_empty' => false )) as $pt){
    $t = new stdClass();
	$t->id   = $pt->term_id;
	$t->slug = urldecode($pt->slug);
	$t->name = $pt->name;
	$tags[]     = $t;
}

$count = 0;

$mu_res = 0;
if(isset($_REQUEST["mass_update_val"])){

  $products_query = new WP_Query( $args );
  $count          = $products_query->found_posts;
  $IDS            = $products_query->get_posts();  
 
  foreach ($IDS as $id) {
	  
 	  if($_REQUEST['mass_update_override']){
	    $override_price     = get_post_meta($id,'_wpsc_special_price',true);
		if(is_numeric($override_price)){
			$override_price = floatval($override_price);
			if($_REQUEST["mass_update_percentage"]){
				update_post_meta($id, '_wpsc_special_price', $override_price * (1 + floatval($_REQUEST["mass_update_val"]) / 100) );
			}else{
				update_post_meta($id, '_wpsc_special_price', $override_price + floatval($_REQUEST["mass_update_val"]));
			}
		}
	  }else{
	    $price              = get_post_meta($id,'_wpsc_price',true);
	    if(is_numeric($price)){
			$price = floatval($price);
			if($_REQUEST["mass_update_percentage"]){
				update_post_meta($id, '_wpsc_price', $price * (1 + floatval($_REQUEST["mass_update_val"]) / 100));
			}else{
				update_post_meta($id, '_wpsc_price', $price + floatval($_REQUEST["mass_update_val"]));
			}
		}
	  }
	  $mu_res++;
  }
  wp_reset_postdata();
}

//$products       = array();

$args['posts_per_page'] = $limit; 
$args['paged'] = $page_no;

$products_query = new WP_Query( $args );
$count          = $products_query->found_posts;	
$IDS            = $products_query->get_posts();	

if($count == 0){
    $IDS = array();
    unset($args['fields']);
    $products_query = new WP_Query( $args );
    $count          = $products_query->found_posts;	
    while($products_query->have_posts()){
	$products_query->next_post();
        $IDS[] = $products_query->post->ID; 
    }     
    wp_reset_postdata();
}

function product_render(&$IDS, $op,&$df = null){
    global $wpdb, $custom_fileds;

	$fcols = array();	
	foreach($custom_fileds as $cfname => $cfield){
		if($cfield->type == "post"){
			$fcols[] = $cfield->source;
		}
	}
	$id_list = implode(",",$IDS);
	if(!$id_list)
		$id_list = 9999999;
	$raw_data = $wpdb->get_results("select ID, post_name ". (!empty($fcols) ? "," . implode(",",$fcols) : "") ." from $wpdb->posts where ID in (". $id_list .")",OBJECT_K); 
 
	
    $p_n = 0;
	foreach($IDS as $id) {
	  
	  $prod = new stdClass();
	  $prod->id         = $id;
	  
	  if(!isset($_REQUEST["do_export"])){
		$prod->type           = get_post_type($id);
		$prod->parent         = get_ancestors($id,'wpsc-product');
		if(!empty($prod->parent))
			$prod->parent = $prod->parent[0];
		else
            $prod->parent = null;	
	  }
	  
	  if(fn_show_filed('sku'))
	  $prod->sku        = get_post_meta($id,'_wpsc_sku',true);
	  
	  if(fn_show_filed('slug'))
		$prod->slug       = urldecode($raw_data[$id]->post_name);
	
      if(fn_show_filed('categories'))	
		$prod->categories = wp_get_object_terms( $id, 'wpsc_product_category', array('fields' => 'ids') );
	  
	  if(!isset($_REQUEST["do_export"]) && $prod->parent){
		if(fn_show_filed('categories'))
			$prod->categories = wp_get_object_terms( $prod->parent, 'wpsc_product_category', array('fields' => 'ids') );
	  }
	  
	  if(isset($_REQUEST["do_export"])){
	    if(fn_show_filed('categories')){	
			$prod->categories_names     = implode(",",wp_get_object_terms( $id, 'wpsc_product_category', array('fields' => 'names') ));
			unset($prod->categories);
		}
	  }
	  
	  if(fn_show_filed('name'))	
		$prod->name               = get_the_title($id);
	  
	  if(fn_show_filed('stock')){	
		  $prod->stock              = get_post_meta($id,'_wpsc_stock',true);
		  if(!$prod->stock)
			$prod->stock = '';
	  }

	  if(fn_show_filed('price')){	
		  $prod->price              = get_post_meta($id,'_wpsc_price',true);
		  
	  }

	  if(fn_show_filed('override_price')){	
		  $prod->override_price     = get_post_meta($id,'_wpsc_special_price',true);
		  
	  }	
	 
	 
	  foreach($custom_fileds as $cfname => $cfield){ 
	   if($cfield->type == "term"){
		if(isset($_REQUEST["do_export"]))
			$prod->{$cfname} = implode(",",wp_get_object_terms($id,$cfield->source, 'names'));
		else{
			if($prod->parent)
				$prod->{$cfname} = wp_get_object_terms($prod->parent,$cfield->source, 'ids');
			else
				$prod->{$cfname} = wp_get_object_terms($id, $cfield->source , 'ids');
		}	
	   }elseif($cfield->type == "meta"){
			$prod->{$cfname} = fn_get_meta_by_path( $id , $cfield->source);
	   }elseif($cfield->type == "post"){
			$prod->{$cfname} = $raw_data[$id]->{$cfield->source};
	   }
	  }

	 
	 
	  if(fn_show_filed('status'))
		$prod->status       = get_post_status($id);
	  
	  $ptrems = get_the_terms($id,'product_tag');
	  
	  if(fn_show_filed('tags')){
		  if(isset($_REQUEST["do_export"])){
			  $prod->tags_names         = null;
			  if($ptrems){
				  foreach((array)$ptrems as $pt){
					if(!isset($prod->tags_names)) 
						$prod->tags_names = array();
						
					$prod->tags_names[] = $pt->name;
				  }
				  $prod->tags_names = implode(",",$prod->tags_names);
			  }
		  }else{
			  $prod->tags               = null;
			  if($ptrems){
				  foreach((array)$ptrems as $pt){
					if(!isset($prod->tags)) 
						$prod->tags = array();
						
					$prod->tags[] = $pt->term_id;
				  }
			  }
		  }
	  }
	  
	  $pr_meta = get_post_meta($id,'_wpsc_product_metadata',true);
	  $dimensions = &$pr_meta['dimensions'];
	  
	  if(fn_show_filed('weight'))
		$prod->weight       = isset($pr_meta['weight']) ? round( fn_convert_unit($pr_meta['weight'],'pound',$pr_meta['weight_unit']) , 2) .' '. $pr_meta['weight_unit'] : "";
	  if(fn_show_filed('height'))
		$prod->height       = isset($dimensions['height']) ? $dimensions['height'] .' '. $dimensions['height_unit'] : "";
	  if(fn_show_filed('width'))
		$prod->width        = isset($dimensions['width']) ? $dimensions['width']  .' '. $dimensions['width_unit'] : "";
	  if(fn_show_filed('length'))
		$prod->length       = isset($dimensions['length']) ? $dimensions['length'] .' '. $dimensions['length_unit'] : "";
	  if(fn_show_filed('taxable'))
		$prod->taxable      = isset($pr_meta['wpec_taxes_taxable_amount']) ? $pr_meta['wpec_taxes_taxable_amount'] : "";
	  if(fn_show_filed('loc_shipping'))
		$prod->loc_shipping = isset($pr_meta['shipping']) ? $pr_meta['shipping']['local'] : "";
	  if(fn_show_filed('int_shipping'))
		$prod->int_shipping = isset($pr_meta['shipping']) ? $pr_meta['shipping']['international'] : "";
		
	  if(fn_show_filed('image') && !isset($_REQUEST["do_export"])){	
		  if(has_post_thumbnail($id)){
			$thumb_id    = get_post_thumbnail_id($id);
			$prod->image = get_post_meta($thumb_id, '_wp_attached_file', true);
		  }else
			$prod->image = null;
	  }
  
 	  if($op == "json"){
	     if($p_n > 0) echo ",";
	     $out = json_encode($prod);
		 if($out)
			echo $out;
		 else
            echo "/*ERROR json_encode product ID $id*/";		 
	  }elseif($op == "export"){
	     if($p_n == 0){
		   $pprops =  (array)$prod;
		   $props = array();
		   foreach( $pprops as $key => $pprop){
			$props[] = $key;
		   }
		   fputcsv($df, $props);
		 }
		 fputcsv($df, (array)$prod);
	  }
	  $p_n++;
	  unset($prod);
	  
	}
};

if(isset($_REQUEST["do_export"])){
	if($_REQUEST["do_export"] = "1"){
	
		$filename = "data_export_" . date("Y-m-d") . ".csv";
		$now = gmdate("D, d M Y H:i:s");
		header("Expires: Tue, 03 Jul 2001 06:00:00 GMT");
		header("Cache-Control: max-age=0, no-cache, must-revalidate, proxy-revalidate");
		header("Last-Modified: {$now} GMT");

		// force download  
		header("Content-Type: application/force-download");
		header("Content-Type: application/octet-stream");
		header("Content-Type: application/download");

		// disposition / encoding on response body
		header("Content-Disposition: attachment;filename={$filename}");
		header("Content-type:application/csv;charset=UTF-8");
		header("Content-Transfer-Encoding: binary");
		echo "\xEF\xBB\xBF"; // UTF-8 BOM
		
		$df = fopen("php://output", 'w');
	   
	    ///////////////////////////////////////////////////
		product_render($IDS,"export",$df);
		///////////////////////////////////////////////////
		
	    fclose($df);
		
		die();
	    exit;  
	    return;
	}
}


?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<script type="text/javascript">
var _wpColorScheme = {"icons":{"base":"#999","focus":"#2ea2cc","current":"#fff"}};
var ajaxurl = '<?php echo $_SERVER['PHP_SELF']; ?>';

function cleanLayout(){
	localStorage.clear();
	doLoad();
	return false;
}

try{
  if(localStorage['dg_wpsc_manualColumnWidths']){
    localStorage['dg_wpsc_manualColumnWidths'] = JSON.stringify( eval(localStorage['dg_wpsc_manualColumnWidths']).map(function(s){
	   if(!s) return null;
	   if(s > 220)
			return 220;
	   return s;	
	}));
  }  
}catch(e){}
</script>
<?php
	
wp_print_scripts('jquery');

if($use_content_editior){
	wp_print_scripts('word-count');
	wp_print_scripts('editor');
	wp_print_scripts('quicktags');
	wp_print_scripts('wplink');
	wp_print_scripts('wpdialogs-popup');
	wp_print_styles('wp-jquery-ui-dialog');
}

if($use_image_picker || $use_content_editior){
	wp_print_scripts('media-upload');
	wp_print_scripts('thickbox');
	wp_print_styles('thickbox');
}
	
?>



<link rel="stylesheet" href="<?php echo $excellikepricechangeforwoocommerceandwpecommercelight_baseurl. 'assets/jquery.spreadsheet.full.css';?>">
<script src="<?php echo $excellikepricechangeforwoocommerceandwpecommercelight_baseurl. 'assets/jquery.spreadsheet.full.js'; ?>" type="text/javascript"></script>
<!--
//FIX IN jquery.handsontable.full.js:

WalkontableTable.prototype.getLastVisibleRow = function () {
  return this.rowFilter.visibleToSource(this.rowStrategy.cellCount - 1);
};

//changed to:

WalkontableTable.prototype.getLastVisibleRow = function () {
  var hsum = 0;
  var sizes_check = jQuery(".htCore tbody tr").toArray().map(function(s){var h = jQuery(s).innerHeight(); hsum += h; return h;});
  var o_size = this.rowStrategy.cellSizesSum;
  
  if(hsum - o_size > 20){
	this.rowStrategy.cellSizes = sizes_check;
	this.rowStrategy.cellSizesSum = hsum - 1;
	this.rowStrategy.cellCount = this.rowStrategy.cellSizes.length;
	this.rowStrategy.remainingSize = hsum - o_size;
  }
	
  return this.rowFilter.visibleToSource(this.rowStrategy.cellCount - 1);
};
-->
<!--
<link rel="stylesheet" href="<?php echo $excellikepricechangeforwoocommerceandwpecommercelight_baseurl.'handsontable/jquery.handsontable.removeRow.css'; ?>">
<script src="<?php echo $excellikepricechangeforwoocommerceandwpecommercelight_baseurl.'handsontable/jquery.handsontable.removeRow.js'; ?>" type="text/javascript"></script>
-->

<link rel="stylesheet" href="<?php echo $excellikepricechangeforwoocommerceandwpecommercelight_baseurl.'/lib/chosen.min.css'; ?>">
<script src="<?php echo $excellikepricechangeforwoocommerceandwpecommercelight_baseurl.'lib/chosen.jquery.min.js'; ?>" type="text/javascript"></script>

<link rel="stylesheet" href="<?php echo $excellikepricechangeforwoocommerceandwpecommercelight_baseurl.'assets/style.css'; ?>">

</head>
<body>

<?php if($use_content_editior){ ?>
<div id="content-editor" >
	<div>
	<?php
		$args = array(
			'textarea_rows' => 15,
			'teeny' => true,
			'quicktags' => true,
			'media_buttons' => false
		);
		 
		wp_editor( '', 'editor', $args );
		_WP_Editors::editor_js();
	?>
	<div class="cmds-editor">
	   <a class="metro-button" id="cmdContentSave" ><?php echo __("Save",'excellikepricechangeforwoocommerceandwpecommercelight'); ?></a>
	   <a class="metro-button" id="cmdContentCancel" ><?php echo __("Cancel",'excellikepricechangeforwoocommerceandwpecommercelight'); ?></a>   
	   <div style="clear:both;" ></div>
	</div>
	</div>
</div>
<?php } ?>

<div class="header">
<ul class="menu">
  <?php if(isset($_REQUEST['pelm_full_screen'])){ ?>
  <li>
   <a class="cmdBackToJoomla" href="<?php echo "admin.php?page=excellikepricechangeforwoocommerceandwpecommercelight-wpsc"; ?>" > <?php echo __("Back to Wordpress",'excellikepricechangeforwoocommerceandwpecommercelight'); ?> </a>
  </li>
  <?php } ?>
  
  <li><span class="undo"><button id="cmdUndo" onclick="undo();" ><?php echo __("Undo",'excellikepricechangeforwoocommerceandwpecommercelight'); ?></button></span></li>
  <li><span class="redo"><button id="cmdRedo" onclick="redo();" ><?php echo __("Redo",'excellikepricechangeforwoocommerceandwpecommercelight'); ?></button></span></li>
  <li>
   <span><span> <?php echo __("Export/Import",'excellikepricechangeforwoocommerceandwpecommercelight'); ?> &#9655;</span></span>
   <ul>
     <li><span><button onclick="do_export();return false;" ><?php echo __("Export CSV",'excellikepricechangeforwoocommerceandwpecommercelight'); ?></button></span></li>
     <li><span><button onclick="do_import();return false;" ><?php echo __("Update prices from CSV",'excellikepricechangeforwoocommerceandwpecommercelight'); ?></button></span></li>
   </ul>
  </li>
  <li>
   <span><span> <?php echo __("Options",'excellikepricechangeforwoocommerceandwpecommercelight'); ?> &#9655;</span></span>
   <ul>
     <li><span><button onclick="cleanLayout();return false;" ><?php echo __("Clean layout cache...",'excellikepricechangeforwoocommerceandwpecommercelight'); ?></button></span></li>
   </ul>
  </li>

  <!--
  <li style="font-weight: bold;">
   <span><a style="color: cyan;font-size: 16px;" href="http://holest.com/index.php/holest-outsourcing/joomla-wordpress/virtuemart-excel-like-product-manager.html">Buy this component!</a></span> 
  </li>
  -->
  <li style="float:right;display:none;" >
   <table>
     <tr><td rowspan="2" ><?php echo __("Input units",'excellikepricechangeforwoocommerceandwpecommercelight');?>:&nbsp;&nbsp;</td><td><?php echo __("Weight",'excellikepricechangeforwoocommerceandwpecommercelight');?></td><td><?php echo __("Height",'excellikepricechangeforwoocommerceandwpecommercelight');?></td><td><?php echo __("Width",'excellikepricechangeforwoocommerceandwpecommercelight');?></td><td><?php echo __("Length",'excellikepricechangeforwoocommerceandwpecommercelight');?></td></tr> 
	 <tr>
		 
		 <td>
			<select class="save-state" id="weight_unit">
				<option value="pound" selected="selected">pounds</option>
				<option value="ounce">ounces</option>
				<option value="gram">grams</option>
				<option value="kilogram">kilograms</option>
			</select>
		 </td>
		 <td>
			<select class="save-state" id="height_unit">
				<option value="in" selected="selected">inches</option>
				<option value="cm">cm</option>
				<option value="meter">meters</option>
			</select>
		 </td>
		 <td>
			<select class="save-state" id="width_unit">
				<option value="in" selected="selected">inches</option>
				<option value="cm">cm</option>
				<option value="meter">meters</option>
			</select>
		 </td>
		 <td>
			<select class="save-state" id="length_unit">
				<option value="in" selected="selected">inches</option>
				<option value="cm">cm</option>
				<option value="meter">meters</option>
			</select> 
		 </td>
	 </tr> 
   </table>
  </li>
</ul>

</div>
<div class="content">
<div class="filter_panel opened">
<span class="filters_label" ><span class="toggler"><span><?php echo __("Filters",'excellikepricechangeforwoocommerceandwpecommercelight');?></span></span></span>
<div class="filter_holder">
  
  
  <div class="filter_option">
     <label><?php echo __("SKU",'excellikepricechangeforwoocommerceandwpecommercelight');?></label>
	 <input placeholder="<?php echo __("Enter part of SKU...",'excellikepricechangeforwoocommerceandwpecommercelight'); ?>" type="text" name="sku" value="<?php echo $sku;?>"/>
  </div>
  
  <div class="filter_option">
     <label><?php echo __("Product Name",'excellikepricechangeforwoocommerceandwpecommercelight');?></label>
	 <input placeholder="<?php echo __("Enter part of name...",'excellikepricechangeforwoocommerceandwpecommercelight'); ?>" type="text" name="product_name" value="<?php echo $product_name;?>"/>
  </div>
  
  <div class="filter_option">
     <label><?php echo __("Category",'excellikepricechangeforwoocommerceandwpecommercelight');?></label>
	 <select data-placeholder="<?php echo __("Chose categories...",'excellikepricechangeforwoocommerceandwpecommercelight'); ?>" class="inputbox" multiple name="product_category" >
		<option value=""></option>
		<?php
		    foreach($categories as $category){
			    $par_ind = '';
				if($category->category_parent){
				  $par = $cat_asoc[$category->category_parent];
				  while($par){
				    $par_ind.= ' - ';
					$par = $cat_asoc[$par->category_parent];
				  }
				}
				echo '<option value="'.$category->category_id.'" >'.$par_ind.$category->category_name.'</option>';
			}
		
		?>
	 </select>
  </div>
  
 <div class="filter_option">
     <label><?php echo __("Tags",'excellikepricechangeforwoocommerceandwpecommercelight');?></label>
	 <select data-placeholder="<?php echo __("Chose tags...",'excellikepricechangeforwoocommerceandwpecommercelight'); ?>" class="inputbox" multiple name="product_tag" >
		<option value=""></option>
		<?php
		    foreach($tags as $tag){
			   echo '<option value="'.$tag->id.'" >'.$tag->name.'</option>';
			}
		
		?>
	 </select>
  </div>
  
  <div class="filter_option">
     <label><?php echo __("Product Status",'excellikepricechangeforwoocommerceandwpecommercelight');?></label>
	 <select data-placeholder="<?php echo __("Chose status...",'excellikepricechangeforwoocommerceandwpecommercelight'); ?>"  class="inputbox" name="product_status" multiple >
	    <option value="" ></option>
		<option value="publish"><?php echo __("Published",'excellikepricechangeforwoocommerceandwpecommercelight');?></option>
		<option value="pending"><?php echo __("Pending",'excellikepricechangeforwoocommerceandwpecommercelight');?></option>
		<option value="draft"><?php echo __("Draft",'excellikepricechangeforwoocommerceandwpecommercelight');?></option>
		<option value="auto-draft"><?php echo __("Auto-draft",'excellikepricechangeforwoocommerceandwpecommercelight');?></option>
		<option value="future"><?php echo __("Future",'excellikepricechangeforwoocommerceandwpecommercelight');?></option>
		<option value="private"><?php echo __("Private",'excellikepricechangeforwoocommerceandwpecommercelight');?></option>
		<option value="inherit"><?php echo __("Inherit",'excellikepricechangeforwoocommerceandwpecommercelight');?></option>
		<option value="trash"><?php echo __("Trash",'excellikepricechangeforwoocommerceandwpecommercelight');?></option>
	 </select>
  </div>
  
  <div class="filter_option">
     <input id="cmdRefresh" type="submit" class="cmd" value="<?php echo __("Refresh",'excellikepricechangeforwoocommerceandwpecommercelight');?>" onclick="doLoad();" />
  </div>
  
  <br/>
  <br/>
  <hr/>
  
  <div class="filter_option">
	  <label><?php echo __("Mass update by filter criteria: ",'excellikepricechangeforwoocommerceandwpecommercelight'); ?></label> 
	  <input style="width:140px;float:left;" placeholder="<?php echo sprintf(__("[+/-]X%s or [+/-]X",'excellikepricechangeforwoocommerceandwpecommercelight'),'%'); ?>" type="text" id="txtMassUpdate" value="" /> 
	  <button id="cmdMassUpdate" class="cmd" onclick="massUpdate(false);return false;" style="float:right;"><?php echo __("Mass update price",'excellikepricechangeforwoocommerceandwpecommercelight'); ?></button>
	  <button id="cmdMassUpdateOverride" class="cmd" onclick="massUpdate(true);return false;" style="float:right;"><?php echo __("Mass update sales price",'excellikepricechangeforwoocommerceandwpecommercelight'); ?></button>
	  
  </div>
  <div style="clear:both;" ></div>
  
</div>
</div>

<div id="dg_wpsc" class="hst_dg_view fixed-<?php echo $plem_settings['fixedColumns']; ?>" style="margin-left:-1px;margin-top:0px;overflow: scroll;background:#FBFBFB;">
</div>

</div>
<div class="footer">
 <div class="pagination">
   <label for="txtLimit" ><?php echo __("Limit:",'excellikepricechangeforwoocommerceandwpecommercelight');?></label><input id="txtlimit" class="save-state" style="width:40px;text-align:center;" value="<?php echo $limit;?>" plem="<?php $arr =(array)$plem_settings; echo reset($arr); ?>"  />
   <?php
       if($limit && ceil($count / $limit) > 1){
	    ?>
	       <input type="hidden" id="paging_page" value="<?php echo $page_no ?>" />	
		   
		<?php
		  if($page_no > 1){
		   ?>
		   <span class="page_number" onclick="setPage(this,1);return false;" ><<</span>
		   <span class="page_number" onclick="setPage(this,'<?php echo ($page_no - 1); ?>');return false;" ><</span>
		   <?php
		  }
		  
	      for($i = 0; $i < ceil($count / $limit); $i++ ){
		    if(($i + 1) < $page_no - 2 ) continue;
			if(($i + 1) > $page_no + 2) {
              echo "<label>...</label>";			  
			  break;
			}
		    ?>
              <span class="page_number <?php echo ($i + 1) == $page_no ? " active " : "";  ?>" onclick="setPage(this,'<?php echo ($i + 1); ?>');return false;" ><?php echo ($i + 1); ?></span>
            <?php			
		  }
		  
		  if($page_no < ceil($count / $limit)){
		   ?>
		   <span class="page_number" onclick="setPage(this,'<?php echo ($page_no + 1); ?>');return false;" >></span>
		   <span class="page_number" onclick="setPage(this,'<?php echo ceil($count / $limit); ?>');return false;" >>></span>
		   <?php
		  }
		  
	   }
   ?>
   <span class="pageination_info"><?php echo sprintf(__("Page %s of %s, total %s products by filter criteria",'excellikepricechangeforwoocommerceandwpecommercelight'),$page_no,ceil($count / $limit),$count); ?></span>
   
 </div>
 
 <span class="note" style="float:right;"><?php echo __("*All changes are instantly autosaved",'excellikepricechangeforwoocommerceandwpecommercelight');?></span>
 <span class="wait save_in_progress" ></span>
 
</div>
<iframe id="frameKeepAlive" style="display:none;"></iframe>

<form id="operationFRM" method="POST" >

</form>

<script type="text/javascript">
var DG          = null;
var tasks      = {};
var variations_skip = <?php echo json_encode($variations_skip); ?>;
var categories = <?php echo json_encode($categories);?>;
var tags       = <?php echo json_encode($tags);?>;
var asoc_cats = {};
var asoc_tags = {};

var ContentEditorCurrentlyEditing = {};
var ImageEditorCurrentlyEditing = {};


window.onbeforeunload = function() {
    try{
		pelmStoreState();
	}catch(e){}
	
    var n = 0;
	for(var key in tasks)
		n++;
     
	if(n > 0){
	  doSave();
	  return "<?php echo __("Transactions ongoing. Plese wait a bit more for them to complete!",'excellikepricechangeforwoocommerceandwpecommercelight');?>";
	}else
	  return;	   
}

for(var c in categories){
  asoc_cats[categories[c].category_id] = categories[c].category_name;
}

for(var t in tags){
  asoc_tags[tags[t].id] = tags[t].name;
}

var keepAliveTimeoutHande = null;
var resizeTimeout
  , availableWidth
  , availableHeight
  , $window = jQuery(window)
  , $dg     = jQuery('#dg_wpsc');
  
$ = jQuery;  

var calculateSize = function () {
  var offset = $dg.offset();
  
  $('div.content').outerHeight(window.innerHeight - $('BODY > DIV.header').outerHeight() - $('BODY > DIV.footer').outerHeight());
  
  availableWidth = $('div.content').innerWidth() - offset.left + $window.scrollLeft() - (jQuery('.filter_panel').innerWidth() + parseInt(jQuery('.filter_panel').css('right')) - 2);
  availableHeight = $('div.content').innerHeight() + 2;
  $('.filter_panel').css('height',(availableHeight) + 'px');
  $('#dg_wpsc').handsontable('render');
};

$window.on('resize', calculateSize);

calculateSize();

jQuery(document).ready(function(){calculateSize();});
jQuery(window).load(function(){calculateSize();});  

jQuery('#frameKeepAlive').blur(function(e){
     e.preventDefault();
	 return false;
   });
   
function setKeepAlive(){
   if(keepAliveTimeoutHande)
	clearTimeout(keepAliveTimeoutHande);
	
   keepAliveTimeoutHande = setTimeout(function(){
	  jQuery('#frameKeepAlive').attr('src',window.location.href + "&keep_alive=1&diff=" + Math.random());
	  setKeepAlive();
   },30000);
}

function setPage(sender,page){
	jQuery('#paging_page').val(page);
	jQuery('.page_number').removeClass('active');
	jQuery(sender).addClass('active');
	doLoad();
	return false;
}

var pending_load = 0;

function getSortProperty(){
    if(!DG)
		DG = $('#dg_wpsc').data('handsontable');
				
    var frozen =  <?php echo $plem_settings['fixedColumns']; ?>;
	if(DG.sortColumn <= frozen)
		return DG.colToProp( DG.sortColumn);
	else
		return DG.colToProp( DG.sortColumn + DG.colOffset());
}

function doLoad(){
    pending_load++;
	if(pending_load < 6){
		var n = 0;
		for(var key in tasks)
			n++;
			
		if(n > 0) {
		  setTimeout(function(){
			doLoad();
		  },2000);
		  return;
		}
	}

    var POST_DATA = {};
	
	POST_DATA.sortOrder            = DG.sortOrder ? "ASC" : "DESC";
	POST_DATA.sortColumn           = getSortProperty();
	POST_DATA.limit                = $('#txtlimit').val();
	POST_DATA.page_no              = $('#paging_page').val();
	
 	POST_DATA.sku                  = $('.filter_option *[name="sku"]').val();
	POST_DATA.product_name         = $('.filter_option *[name="product_name"]').val();
	POST_DATA.product_tag          = $('.filter_option *[name="product_tag"]').val();
	POST_DATA.product_category     = $('.filter_option *[name="product_category"]').val();
	POST_DATA.product_status       = $('.filter_option *[name="product_status"]').val();
	
    jQuery('#operationFRM').empty();
	
	for(var key in POST_DATA){
		if(POST_DATA[key])
			jQuery('#operationFRM').append("<INPUT type='hidden' name='" + key + "' value='" + POST_DATA[key] + "' />");
	}
	
    jQuery('#operationFRM').submit();
}

function massUpdate(update_override){
    if(!jQuery.trim(jQuery('#txtMassUpdate').val())){
	  alert("<?php echo __("Enter value first!",'excellikepricechangeforwoocommerceandwpecommercelight');?>");
	  return;
	} 

	if(confirm("<?php echo __("Update proiduct price for all products matched by filter criteria (this operation can not be undone)?",'excellikepricechangeforwoocommerceandwpecommercelight');?>")){
		var POST_DATA = {};
		
		POST_DATA.mass_update_val        = parseFloat(jQuery('#txtMassUpdate').val()); 
		POST_DATA.mass_update_percentage = (jQuery('#txtMassUpdate').val().indexOf("%") >= 0) ? 1 : 0;
		POST_DATA.mass_update_override   = update_override ? '1' : '0';
		
		POST_DATA.sortOrder            = DG.sortOrder ? "ASC" : "DESC";
		POST_DATA.sortColumn           = getSortProperty();
		POST_DATA.limit                = $('#txtlimit').val();
		POST_DATA.page_no               = $('#paging_page').val();
		
		POST_DATA.sku                  = $('.filter_option *[name="sku"]').val();
		POST_DATA.product_name         = $('.filter_option *[name="product_name"]').val();
		POST_DATA.product_tag          = $('.filter_option *[name="product_tag"]').val();
		POST_DATA.product_category     = $('.filter_option *[name="product_category"]').val();
		POST_DATA.product_status       = $('.filter_option *[name="product_status"]').val();
		
		
		jQuery('#operationFRM').empty();
		
		for(var key in POST_DATA){
			if(POST_DATA[key])
				jQuery('#operationFRM').append("<INPUT type='hidden' name='" + key + "' value='" + POST_DATA[key] + "' />");
		}
		jQuery('#operationFRM').submit();
	}
}

var saveHandle = null;
var save_in_progress = false;
var id_index = null;

function doSave(){
	var update_data = JSON.stringify(tasks); 	   
	save_in_progress = true;
	jQuery(".save_in_progress").show();

	jQuery.ajax({
	url: window.location.href + "&DO_UPDATE=1&diff=" + Math.random(),
	type: "POST",
	dataType: "json",
	data: update_data,
	success: function (data) {
	    if(!id_index){
		    id_index = [];
			var n = 0;
		    DG.getData().map(function(s){
			  if(id_index[s.id])
			    id_index[s.id].ind = n;
			  else
				id_index[s.id] = {ind:n,ch:[]}; 
			  
              if(s.parent){
				  if(id_index[s.parent])
					id_index[s.parent].ch.push(n);
				  else
					id_index[s.parent] = {ind:-1,ch:[n]}; 
			  }  			  
			  n++;
			});
		}	
		
		//date.id
		
		var updated = eval("(" + update_data + ")");
		for(key in updated){
		 if(tasks[key]){
		    //Update inherited values
			try{
				if(data[0].id && data[0].success){
					var inf = id_index[data[0].id];
					if(inf.ind >= 0 && inf.ch.length > 0){
						for(prop in tasks[key]){
							if($.inArray(prop, variations_skip) >= 0){
							   for(ch in inf.ch){
							      DG.setDataAtRowProp( inf.ch[ch] , prop ,tasks[key][prop] ,'skip');
							   }
							}
						}	
					}
				}
			}catch(e){} 
		 
			if(JSON.stringify(tasks[key]) == JSON.stringify(updated[key]))
				delete tasks[key];
		 }
		}

		save_in_progress = false;
		jQuery(".save_in_progress").hide();

	},
	error: function(a,b,c){

		save_in_progress = false;
		jQuery(".save_in_progress").hide();
		callSave();
		
	}
	});
}

function callSave(){
    if(saveHandle){
	   clearTimeout(saveHandle);
	   saveHandle = null;
	}
	
	saveHandle = setTimeout(function(){
	   saveHandle = null;
	   
	   if(save_in_progress){
	       setTimeout(function(){
			callSave();
		   },3000);
		   return;
	   }
       doSave();
	},3000);
}

function undo(){
	DG.undo();
}

function redo(){
	DG.redo();
}

var strip_helper = document.createElement("DIV");
function strip(html){
   strip_helper.innerHTML = html;
   return strip_helper.textContent || strip_helper.innerText || "";
}

jQuery(document).ready(function(){

    var CustomSelectEditor = Handsontable.editors.BaseEditor.prototype.extend();
	CustomSelectEditor.prototype.init = function(){
	   // Create detached node, add CSS class and make sure its not visible
	   this.select = $('<select multiple="1" ></select>')
		 .addClass('htCustomSelectEditor')
		 .hide();
		 
	   // Attach node to DOM, by appending it to the container holding the table
	   this.instance.rootElement.append(this.select);
	};
	
	// Create options in prepare() method
	CustomSelectEditor.prototype.prepare = function(){
       
		//Remember to invoke parent's method
		Handsontable.editors.BaseEditor.prototype.prepare.apply(this, arguments);
		
		var options = this.cellProperties.selectOptions || [];

		var optionElements = options.map(function(option){
			var optionElement = $('<option />');
			if(typeof option === typeof {}){
			  optionElement.val(option.value);
			  optionElement.html(option.name);
			}else{
			  optionElement.val(option);
			  optionElement.html(option);
			}

			return optionElement
		});

		this.select.empty();
		this.select.append(optionElements);
		
		
		var widg = this.select.next();
		var self = this;
		if(!widg.is('.chosen-container')){
			if(!this.cellProperties.select_multiple){
			   this.select.removeAttr('multiple');
			   this.select.change(function(){
					self.finishEditing()
					$('#dg_wpsc').handsontable("selectCell", self.row , self.col);					
			   });
			}			   

			var chos;

			if(this.cellProperties.allow_random_input)
				chos = this.select.chosen({
					create_option: true,
					create_option_text: 'value',
					persistent_create_option: true,
					skip_no_results: true
				}).data('chosen');
			else
				chos = this.select.chosen().data('chosen');

			chos.container.bind('keyup', function (event) {
			   
			   if(event.keyCode == 13){
				  var src_inp = jQuery(this).find('LI.search-field > INPUT[type="text"]:first');
				  if(src_inp[0])
					if(src_inp.val() == ''){
					   event.stopImmediatePropagation();
					   event.preventDefault();
					   self.finishEditing()
					   self.focus();
					   
					   $('#dg_wpsc').handsontable("selectCell", self.row + 1, self.col);
					}
			   }
			});
		}
	};
	
	
	CustomSelectEditor.prototype.getValue = function () {
	   return this.select.val() || [];
	};

	CustomSelectEditor.prototype.setValue = function (value) {
	   if(!(value instanceof Array))
		value = value.split(',');
	   
	   this.select.val(value);
	   this.select.trigger("chosen:updated");
	};

	CustomSelectEditor.prototype.open = function () {
		//sets <select> dimensions to match cell size
		
		var widg = this.select.next();
		widg.css({
		   height: $(this.TD).height(),
		   'min-width' : $(this.TD).outerWidth() > 250 ? $(this.TD).outerWidth() : 250
		});
		
		widg.find('LI.search-field > INPUT').css({
		   'min-width' : $(this.TD).outerWidth() > 250 ? $(this.TD).outerWidth() : 250
		});

		//display the list
		widg.show();

		//make sure that list positions matches cell position
		widg.offset($(this.TD).offset());
	};
	
	CustomSelectEditor.prototype.focus = function () {
	     this.instance.listen();
    };

	CustomSelectEditor.prototype.close = function () {
		 this.select.next().hide();
	};
	
	var clonableARROW = document.createElement('DIV');
	clonableARROW.className = 'htAutocompleteArrow';
	clonableARROW.appendChild(document.createTextNode('\u25BC'));
	
	var clonableEDIT = document.createElement('DIV');
	clonableEDIT.className = 'htAutocompleteArrow';
	clonableEDIT.appendChild(document.createTextNode('\u270E'));
	
	var clonableIMAGE = document.createElement('DIV');
	clonableIMAGE.className = 'htAutocompleteArrow';
	clonableIMAGE.appendChild(document.createTextNode('\u27A8'));

		
	var CustomSelectRenderer = function (instance, td, row, col, prop, value, cellProperties) {
	    try{
		  
		var ARROW = clonableARROW.cloneNode(true); //this is faster than createElement

		Handsontable.renderers.TextRenderer(instance, td, row, col, prop, value, cellProperties);
		
		var fc = td.firstChild;
		while(fc) {
			td.removeChild( fc );
			fc = td.firstChild;
		}
		td.appendChild(ARROW); 
		
		if(value){
		    if(cellProperties.select_multiple){ 
				var rval = value;
				if(!(rval instanceof Array))
					rval = rval.split(',');
				
				td.appendChild(document.createTextNode(rval.map(function(s){ 
				        if(cellProperties.dictionary[s])
							return cellProperties.dictionary[s];
						else
						    return s;
					}).join(', ')
				));
			}else{
			    td.appendChild(document.createTextNode(cellProperties.dictionary[value] || value));
			}
		}else{
			$(td).html('');
		}
		
		Handsontable.Dom.addClass(td, 'htAutocomplete');

		if (!td.firstChild) { //http://jsperf.com/empty-node-if-needed
		  //otherwise empty fields appear borderless in demo/renderers.html (IE)
		  td.appendChild(document.createTextNode('\u00A0')); //\u00A0 equals &nbsp; for a text node
		  //this is faster than innerHTML. See: https://github.com/warpech/jquery-handsontable/wiki/JavaScript-&-DOM-performance-tips
		}

		if (!instance.acArrowListener) {
		  //not very elegant but easy and fast
		  instance.acArrowListener = function () {
			instance.view.wt.getSetting('onCellDblClick');
		  };

		  instance.rootElement.on('mousedown', '.htAutocompleteArrow', instance.acArrowListener); //this way we don't bind event listener to each arrow. We rely on propagation instead

		}
		}catch(e){
			$(td).html('');
		}
	};
	
	
	/////////////////////////////////////////////////////////////////////////////////////////
	jQuery('#content-editor #cmdContentSave').click(function(){
	   DG.setDataAtRowProp( ContentEditorCurrentlyEditing.row, 
	                        ContentEditorCurrentlyEditing.prop, 
							jQuery('#content-editor textarea.wp-editor-area:visible')[0] ? (jQuery('#content-editor textarea.wp-editor-area:visible').val() || '') : (jQuery('#content-editor #editor_ifr').contents().find('BODY').html() || ''),
							''
						  );
							
	   jQuery('#content-editor').css('top','110%');
	});
	
	jQuery('#content-editor #cmdContentCancel').click(function(){
	   jQuery('#content-editor').css('top','110%');
	});
	
	var customContentEditor = Handsontable.editors.BaseEditor.prototype.extend();
	customContentEditor.prototype.open = function () {
		ContentEditorCurrentlyEditing.row  = this.row; 
		ContentEditorCurrentlyEditing.col  = this.col; 
		ContentEditorCurrentlyEditing.prop = this.prop; 
		jQuery('#content-editor').css('top','0%');
		
		DG.selectCell(ContentEditorCurrentlyEditing.row,ContentEditorCurrentlyEditing.col);
	};
	
	customContentEditor.prototype.getValue = function () {
	   if(jQuery('#content-editor textarea.wp-editor-area:visible')[0])
	      return jQuery('#content-editor textarea.wp-editor-area:visible').val() || '';
	   else
          return jQuery('#content-editor #editor_ifr').contents().find('BODY').html() || '';	   
	};

	customContentEditor.prototype.setValue = function (value) {
		jQuery('#content-editor textarea.wp-editor-area').val(value || "");
		jQuery('#content-editor #editor_ifr').contents().find('BODY').html(value || "");
	    this.finishEditing();
	};
	
	customContentEditor.prototype.focus = function () { this.instance.listen();};
	customContentEditor.prototype.close = function () {};
	///////////////////////////////////////////////////////////////////////////////////////////
	
	var customImageEditor = Handsontable.editors.BaseEditor.prototype.extend();
	customImageEditor.prototype.open = function () {
	    ImageEditorCurrentlyEditing.row  = this.row; 
		ImageEditorCurrentlyEditing.col  = this.col; 
		ImageEditorCurrentlyEditing.prop = this.prop; 
		
		tb_show('', 'media-upload.php?type=image&amp;post_id=' + DG.getDataAtRowProp(this.row,'id') + '&amp;TB_iframe=1&amp;flash=0');
		
		jQuery('#TB_iframeContent').load(function(){
		    var doc = jQuery('#TB_iframeContent').contents()[0];
			
			var otherhead = jQuery('#TB_iframeContent').contents().find('head')[0];
			var link = doc.createElement("link");
			link.setAttribute("rel", "stylesheet");
			link.setAttribute("type", "text/css");
			link.setAttribute("href", "<?php echo $excellikepricechangeforwoocommerceandwpecommercelight_baseurl; ?>assets/style.css");
			otherhead.appendChild(link);

            var src = doc.createElement("script");
			src.setAttribute("type", "text/javascript");
			src.setAttribute("href", "<?php echo $excellikepricechangeforwoocommerceandwpecommercelight_baseurl; ?>lib/script.js");
			otherhead.appendChild(src);			
		});
	};
	
	window.customImageEditorSave = function(id,url) {
	    DG.setDataAtRowProp( 
		                     ImageEditorCurrentlyEditing.row, 
	                         ImageEditorCurrentlyEditing.prop, 
							 url,
							 ''
						   );
		tb_remove();
		DG.selectCell(ImageEditorCurrentlyEditing.row,ImageEditorCurrentlyEditing.col);
	};
	
	customImageEditor.prototype.getValue = function () {
		return ImageEditorCurrentlyEditing.value;
	};
	
	customImageEditor.prototype.setValue = function ( value ) {
		ImageEditorCurrentlyEditing.value = value; 
		this.finishEditing(); 
	};
	
	customImageEditor.prototype.focus = function () { this.instance.listen(); };
	customImageEditor.prototype.close = function () {};
	
	///////////////////////////////////////////////////////////////////////////////////////////

	
	
	var unitEditor = Handsontable.editors.TextEditor.prototype.extend();
	unitEditor.prototype.getValue = function () {
	    if(!this.INPUT)
			this.INPUT = $(this.TEXTAREA); 
			
		if(!this.INPUT.val())
			return '';
		else
		    var value = this.INPUT.val().replace(' ',''); 
			if(String(parseFloat(value)) == value)
				return this.INPUT.val() + ' ' + this.INPUT.attr("unit");
			else{
				var val   = parseFloat(value);
			    
				var unit  = value.replace(val,'').replace(' ','');
				var units = [];
				if(typeof this.cellProperties.unit == typeof {} || this.cellProperties.unit.indexOf('.') >= 0 || this.cellProperties.unit.indexOf('#') >= 0 )
				  units    = $(this.cellProperties.unit + ', ' + this.cellProperties.unit + ' *').toArray().map(function(o){
				     var o = $(o);
				     if(!o.attr('value'))
					   return null;
				     return o.attr('value');
				  });
				else
				  units[0] = this.cellProperties.unit;
				
                var nunit = '';				
				for(var ind in units){
				  if(units[ind])
					  if(unit.toLowerCase() == units[ind].toLowerCase()){
						nunit = units[ind];
						break;
					  }
				}
			
				if(!nunit)
					nunit = this.INPUT.attr("unit");
				
				return val + ' ' + nunit;
			}
                			
	};
	
	unitEditor.prototype.setValue = function (value) {
		if(!this.INPUT)
			this.INPUT = $(this.TEXTAREA);
			
		this.INPUT.val('');//clean;
	    
		var val  = '';
	    var unit = '';
		
		if(!value || String(parseFloat(value)) == value){
			val   = parseFloat(value);
		    if(typeof this.cellProperties.unit == typeof {} || this.cellProperties.unit.indexOf('.') >= 0 || this.cellProperties.unit.indexOf('#') >= 0 )
			  unit  = $(this.cellProperties.unit).val();
			else
			  unit  = this.cellProperties.unit;
		}else{
		    val   = parseFloat(value); 
		    unit  = value.replace(val,'').replace(' ','');
		}
		
		this.INPUT.attr("unit",unit);
		if(!isNaN(val))
			this.INPUT.val(val);
	};
	
	var centerCheckboxRenderer = function (instance, td, row, col, prop, value, cellProperties) {
	  Handsontable.renderers.CheckboxRenderer.apply(this, arguments);
	  $(td).css({
		'text-align': 'center',
		'vertical-align': 'middle'
	  });
	};

	var centerTextRenderer = function (instance, td, row, col, prop, value, cellProperties) {
	  Handsontable.renderers.TextRenderer.apply(this, arguments);
	  $(td).css({
		'text-align': 'center',
		'vertical-align': 'middle'
	  });
	};
	
	var customContentRenderer = function (instance, td, row, col, prop, value, cellProperties) {
		try{
			arguments[5] = strip(value); 
			Handsontable.renderers.TextRenderer.apply(this, arguments);
			Handsontable.Dom.addClass(td, 'htContent');
			td.insertBefore(clonableEDIT.cloneNode(true), td.firstChild);
			if (!td.firstChild) { //http://jsperf.com/empty-node-if-needed
			  td.appendChild(document.createTextNode('\u00A0')); //\u00A0 equals &nbsp; for a text node
			}

			if (!instance.acArrowListener) {
			  instance.acArrowListener = function () {
				instance.view.wt.getSetting('onCellDblClick');
			  };
			  instance.rootElement.on('mousedown', '.htAutocompleteArrow', instance.acArrowListener); //this way we don't bind event listener to each arrow. We rely on propagation instead
			}
		}catch(e){
			$(td).html('');
		}
	};
	
	var customImageRenderer = function (instance, td, row, col, prop, value, cellProperties) {
		try{
			Handsontable.renderers.TextRenderer.apply(this, arguments);
			Handsontable.Dom.addClass(td, 'htImage');
			td.insertBefore(clonableIMAGE.cloneNode(true), td.firstChild);
			if (!td.firstChild) { //http://jsperf.com/empty-node-if-needed
			  td.appendChild(document.createTextNode('\u00A0')); //\u00A0 equals &nbsp; for a text node
			}

			if (!instance.acArrowListener) {
			  instance.acArrowListener = function () {
				instance.view.wt.getSetting('onCellDblClick');
			  };
			  instance.rootElement.on('mousedown', '.htAutocompleteArrow', instance.acArrowListener); //this way we don't bind event listener to each arrow. We rely on propagation instead
			}
		}catch(e){
			$(td).html('');
		}
	};

	
	$('#dg_wpsc').handsontable({
	  data: [<?php product_render($IDS,"json");?>],
	  minSpareRows: 0,
	  colHeaders: true,
	  rowHeaders: true,
	  contextMenu: false,
	  manualColumnResize: true,
	  manualColumnMove: true,
	  columnSorting: true,
	  persistentState: true,
	  variableRowHeights: false,
	  fillHandle: 'vertical',
	  currentRowClassName: 'currentRow',
      currentColClassName: 'currentCol',
	  fixedColumnsLeft: <?php echo $plem_settings['fixedColumns']; ?>,
	  //stretchH: 'all',
	  colWidths:[80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80,80],
	  width: function () {
		if (availableWidth === void 0) {
		  calculateSize();
		}
		return availableWidth ;
	  },
	  height: function () {
		if (availableHeight === void 0) {
		  calculateSize();
		}
		return availableHeight;
	  },
	  colHeaders:[
		"ID"
		<?php if(fn_show_filed('name')) echo  ',"'.__("Product Name",'excellikepricechangeforwoocommerceandwpecommercelight').'"';?>
		<?php if(fn_show_filed('sku')) echo  ',"'. __("SKU",'excellikepricechangeforwoocommerceandwpecommercelight').'"';?>
		<?php if(fn_show_filed('slug')) echo  ',"'. __("Slug",'excellikepricechangeforwoocommerceandwpecommercelight').'"';?>
		<?php if(fn_show_filed('categories')) echo  ',"'. __("Category",'excellikepricechangeforwoocommerceandwpecommercelight').'"';?>
		<?php if(fn_show_filed('stock')) echo  ',"'. __("Stock",'excellikepricechangeforwoocommerceandwpecommercelight').'"';?>
		<?php if(fn_show_filed('price')) echo  ',"'. __("Price",'excellikepricechangeforwoocommerceandwpecommercelight').'"';?>
		<?php if(fn_show_filed('override_price')) echo  ',"'. __("Sales price",'excellikepricechangeforwoocommerceandwpecommercelight').'"';?>
		<?php if(fn_show_filed('tags')) echo  ',"'. __("Tags",'excellikepricechangeforwoocommerceandwpecommercelight').'"';?>
		<?php if(fn_show_filed('status')) echo  ',"'. __("Status",'excellikepricechangeforwoocommerceandwpecommercelight').'"';?>
		<?php if(fn_show_filed('weight')) echo  ',"'. __("Weight",'excellikepricechangeforwoocommerceandwpecommercelight').'"';?>
		<?php if(fn_show_filed('height')) echo  ',"'. __("Height",'excellikepricechangeforwoocommerceandwpecommercelight').'"';?>
		<?php if(fn_show_filed('width')) echo  ',"'. __("Width",'excellikepricechangeforwoocommerceandwpecommercelight').'"';?>
		<?php if(fn_show_filed('length')) echo  ',"'. __("Length",'excellikepricechangeforwoocommerceandwpecommercelight').'"';?>
		<?php if(fn_show_filed('image')) echo ',"'.__("Image",'excellikepricechangeforwoocommerceandwpecommercelight').'"';?>
		<?php if(fn_show_filed('taxable')) echo  ',"'. __("Taxable",'excellikepricechangeforwoocommerceandwpecommercelight').'"';?>
		<?php if(fn_show_filed('loc_shipping')) echo  ',"'. __("Local ship.",'excellikepricechangeforwoocommerceandwpecommercelight').'"';?>
		<?php if(fn_show_filed('int_shipping')) echo  ',"'. __("Int. ship.",'excellikepricechangeforwoocommerceandwpecommercelight').'"';?>
		<?php
			foreach($custom_fileds as $cfname => $cfield){ 
			   echo ',"'.__($cfield->title,'excellikepricechangeforwoocommerceandwpecommercelight').'"';
			}
        ?>		
	  ],
	  columns: [
	   { data: "id", readOnly: true, type: 'numeric' }
	  <?php if(fn_show_filed('name')){ ?>,{ data: "name"  , readOnly: true}<?php } ?>
	  <?php if(fn_show_filed('sku')){ ?>,{ data: "sku" , readOnly: true}<?php } ?>
	  <?php if(fn_show_filed('slug')){ ?>,{ data: "slug", type: 'text', readOnly: true  }<?php } ?>
	  <?php if(fn_show_filed('categories')){ ?>,{
	    data: "categories",
		readOnly: true,
	    editor: CustomSelectEditor.prototype.extend(),
		renderer: CustomSelectRenderer,
		select_multiple: true,
		dictionary: asoc_cats,
        selectOptions: (!categories) ? [] : categories.map(function(source){
						   return {
							 "name": source.category_name , 
							 "value": source.category_id
						   }
						})
	   }<?php } ?>
	  <?php if(fn_show_filed('stock')){ ?>,{ data: "stock" ,type: 'numeric',format: '0', renderer: centerTextRenderer, readOnly: true }<?php } ?>
	  <?php if(fn_show_filed('price')){ ?>,{ data: "price"  ,type: 'numeric',format: '0<?php echo substr($_num_sample,1,1);?>00'}<?php } ?>
	  <?php if(fn_show_filed('override_price')){ ?>,{ data: "override_price"  ,type: 'numeric',format: '0<?php echo substr($_num_sample,1,1);?>00'}<?php } ?>	   
	  <?php if(fn_show_filed('tags')){ ?>,{
	    data: "tags",
		readOnly: true,
	    editor: CustomSelectEditor.prototype.extend(),
		renderer: CustomSelectRenderer,
		select_multiple: true,
		dictionary: asoc_tags,
        selectOptions: (!tags) ? [] : tags.map(function(source){
						   return {
							 "name": source.name , 
							 "value": source.id
						   }
						})
	   }<?php } ?>
	  <?php if(fn_show_filed('status')){ ?>,{ 
	     data: "status", 
		 readOnly: true,
         type: 'dropdown',
         source: [   'publish'
					,'pending'
					,'draft'
					,'auto-draft'
					,'future'
					,'private'
					,'inherit'
					,'trash']
	   }<?php } ?>
	  <?php if(fn_show_filed('weight')){ ?>,{ data: "weight", editor: unitEditor, unit: '#weight_unit', readOnly: true }<?php } ?>
	  <?php if(fn_show_filed('height')){ ?>,{ data: "height", editor: unitEditor, unit: '#height_unit', readOnly: true }<?php } ?>
	  <?php if(fn_show_filed('width')){ ?>,{ data: "width", editor: unitEditor, unit: '#width_unit', readOnly: true }<?php } ?>
	  <?php if(fn_show_filed('length')){ ?>,{ data: "length", editor: unitEditor, unit: '#length_unit', readOnly: true }<?php } ?>
	  
	  <?php if(fn_show_filed('image')){ ?>,{ 
		data: "image", 
		readOnly: true,
        editor: customImageEditor.prototype.extend(),
		renderer: customImageRenderer
	  }<?php } ?>

	  <?php if(fn_show_filed('taxable')){ ?>,{ data: "taxable", type: 'numeric',format: '0<?php echo substr($_num_sample,1,1);?>00', readOnly: true }<?php } ?>
	  <?php if(fn_show_filed('loc_shipping')){ ?>,{ data: "loc_shipping", type: 'numeric',format: '0<?php echo substr($_num_sample,1,1);?>00', readOnly: true }<?php } ?>
	  <?php if(fn_show_filed('int_shipping')){ ?>,{ data: "int_shipping", type: 'numeric',format: '0<?php echo substr($_num_sample,1,1);?>00', readOnly: true }<?php } ?>
	  
  <?php foreach($custom_fileds as $cfname => $cfield){ 
		 if($cfname->type == "term"){?>
			,{ 
			   data: "<?php echo $cfield->name;?>",
			   readOnly: true,
			   editor: CustomSelectEditor.prototype.extend(),
			   renderer: CustomSelectRenderer,
			   select_multiple: <?php echo $cfname->options->multiple ? "true" : "false" ?>,
			   allow_random_input: <?php echo $cfname->options->allownew ? "true" : "false" ?>,
			   selectOptions: <?php echo json_encode($cfname->terms);?>
			 }
 <?php }else{ ?>
			,{ 
			   data: "<?php echo $cfield->name;?>"
			   , readOnly: true
			   <?php
			   if($cfield->options->formater == "content"){?>
				, editor: customContentEditor.prototype.extend()
				, renderer: customContentRenderer
			   <?php
			   }elseif($cfield->options->formater == "checkbox"){
				  echo ',type: "checkbox"'; 
				  if($cfname->options->checked_value) echo ',checkedTemplate: "'.$cfield->options->checked_value.'"'; 
				  if($cfname->options->unchecked_value) echo ',uncheckedTemplate: "'.$cfield->options->unchecked_value.'"'; 
			   }elseif($cfield->options->formater == "dropdown"){
				  echo ',type: "autocomplete", strict: ' . ($cfield->options->strict ? "true" : "false");
				  echo ',source:' ;
				  $vals = str_replace(", ",",",$cfield->options->values);
				  $vals = str_replace(", ",",",$vals);
				  $vals = str_replace(" ,",",",$vals);
				  $vals = str_replace(", ",",",$vals);
				  $vals = str_replace(" ,",",",$vals);
				  $vals = explode(",",$vals);
				  echo json_encode($vals);
			   }else{
				  if($cfield->options->format == "integer") echo  ',type: "numeric"';
				  elseif($cfield->options->format == "decimal") echo  ',type: "numeric", format: "0'.substr($_num_sample,1,1).'00"';
			   }
			   ?>				   
			 }
		<?php }
		} ?>

	  
	  ]
	  ,afterChange: function (change, source) {
	    if(!DG)
			DG = $('#dg_wpsc').data('handsontable');
		
		if (source === 'loadData') return;
		if (source === 'skip') return;
		
		change.map(function(data){
		    if(!data)
			  return;
			var id = DG.getDataAtRowProp (data[0],'id');	
			var prop = data[1];
			var val  = data[3];
			if(!tasks[id])
				tasks[id] = {};
			tasks[id][prop] = val;
			DG.view.wt.wtSettings.instance.rowHeightCache[data[0]] = DG.$table.find(' > TBODY > TR:eq(' + data[0] + ')').innerHeight();

		});
		callSave();
	  }
	  ,afterColumnResize: function(currentCol, newSize){
		if(!DG)
			DG = $('#dg_wpsc').data('handsontable');
			
	    DG.view.wt.wtSettings.instance.rowHeightCache = DG.$table.find(' > TBODY > TR').toArray().map(function(s){return $(s).innerHeight();}); 
	    DG.forceFullRender = true;
        DG.view.render(); //updates all
	  }
	  ,cells: function (row, col, prop) {
	    if(!DG)
			DG = $('#dg_wpsc').data('handsontable');
			
	    var row_data = DG.getDataAtRow(row); 
		if(!row_data)
			return;
	    if(row_data.parent){
		    if($.inArray(prop, variations_skip) >= 0){
				this.readOnly = true;
			}
		}
		
	  }

	  
	});
	
	if(!DG)
		DG = $('#dg_wpsc').data('handsontable');
	
	setKeepAlive();
	
	jQuery('.filters_label').click(function(){
		if( jQuery(this).parent().is('.opened')){
			jQuery(this).parent().removeClass('opened').addClass('closed');
		}else{
			jQuery(this).parent().removeClass('closed').addClass('opened');
		}
		jQuery(window).trigger('resize');
	});
	
	jQuery(window).load(function(){
		jQuery(window).trigger('resize');
	});
	
	
	if('<?php echo $product_category;?>') jQuery('.filter_option *[name="product_category"]').val("<?php if($product_category)echo implode(",",$product_category);?>".split(','));
	if('<?php echo $product_tag;?>') jQuery('.filter_option *[name="product_tag"]').val("<?php if($product_tag) echo implode(",",$product_tag);?>".split(','));
	if('<?php echo $product_status;?>') jQuery('.filter_option *[name="product_status"]').val("<?php if($product_status) echo implode(",",$product_status);?>".split(','));
	
	jQuery('SELECT[name="product_category"]').chosen();
	jQuery('SELECT[name="product_tag"]').chosen();
	jQuery('SELECT[name="product_status"]').chosen();
	
	
});



  <?php
    if($mu_res){
	   $upd_val = $_REQUEST["mass_update_val"].(  $_REQUEST["mass_update_percentage"] ? "%" : "" );
	   ?>
	   jQuery(window).load(function(){
	   alert('<?php echo sprintf(__("Proiduct price for all products matched by filter criteria is changed by %s",'excellikepricechangeforwoocommerceandwpecommercelight'),$upd_val); ?>');
	   });
	   <?php
	}
	
	if($import_count){
	   ?>
	   jQuery(window).load(function(){
	   alert('<?php echo sprintf(__("%s products updated prices form imported file!",'excellikepricechangeforwoocommerceandwpecommercelight'),$import_count); ?>');
	   });
	   <?php
	}
	
  ?>


function do_export(){
    var link = window.location.href + "&do_export=1" ;
   
    var QUERY_DATA = {};
	QUERY_DATA.sortOrder            = DG.sortOrder ? "ASC" : "DESC";
	QUERY_DATA.sortColumn           = getSortProperty();
	
	QUERY_DATA.limit                = "9999999999";
	QUERY_DATA.page_no              = "1";
	
	QUERY_DATA.sku                  = $('.filter_option *[name="sku"]').val();
	QUERY_DATA.product_name         = $('.filter_option *[name="product_name"]').val();
	QUERY_DATA.product_tag          = $('.filter_option *[name="product_tag"]').val();
	QUERY_DATA.product_category     = $('.filter_option *[name="product_category"]').val();
	QUERY_DATA.product_status       = $('.filter_option *[name="product_status"]').val();
	
	for(var key in QUERY_DATA){
		if(QUERY_DATA[key])
			link += ("&" + key + "=" + QUERY_DATA[key]);
	}
	
	window.location =  link;
    return false;
}

function do_import(){
    var import_panel = jQuery("<div class='import_form'><form method='POST' enctype='multipart/form-data'><span><?php echo __("Select .CSV file to update prices/stock from.<br>(To void regular or sales price remove coresponding column from CSV file)",'excellikepricechangeforwoocommerceandwpecommercelight'); ?></span><br/><label for='file'><?php echo __("File:",'excellikepricechangeforwoocommerceandwpecommercelight'); ?></label><input type='file' name='file' id='file' /><br/><br/><button class='cmdImport' ><?php echo __("Import",'excellikepricechangeforwoocommerceandwpecommercelight'); ?></button><button class='cancelImport'><?php echo __("Cancel",'excellikepricechangeforwoocommerceandwpecommercelight'); ?></button></form></div>"); 
    import_panel.appendTo(jQuery("BODY"));
	
	import_panel.find('.cancelImport').click(function(){
		import_panel.remove();
		return false;
	});
	
	import_panel.find('.cmdImport').click(function(){
		if(!jQuery("#file").val()){
		  alert('<?php echo __("Enter value first!",'excellikepricechangeforwoocommerceandwpecommercelight');?>');
		  return false;
		}
	    var frm = import_panel.find('FORM');
		var POST_DATA = {};
		
		POST_DATA.do_import            = "1";
		POST_DATA.sortOrder            = DG.sortOrder ? "ASC" : "DESC";
		POST_DATA.sortColumn           = getSortProperty();
		POST_DATA.limit                = $('#txtlimit').val();
		POST_DATA.page_no               = $('#paging_page').val();
		
		POST_DATA.sku                  = $('.filter_option *[name="sku"]').val();
		POST_DATA.product_name         = $('.filter_option *[name="product_name"]').val();
		POST_DATA.product_tag          = $('.filter_option *[name="product_tag"]').val();
		POST_DATA.product_category     = $('.filter_option *[name="product_category"]').val();
		POST_DATA.product_status       = $('.filter_option *[name="product_status"]').val();
		
		for(var key in POST_DATA){
			if(POST_DATA[key])
				frm.append("<INPUT type='hidden' name='" + key + "' value='" + POST_DATA[key] + "' />");
		}
			
		frm.submit();
		return false;
	});
}

</script>
<script src="<?php echo $excellikepricechangeforwoocommerceandwpecommercelight_baseurl.'lib/script.js'; ?>" type="text/javascript"></script>
</body>
</html>
<?php
exit;
?>
