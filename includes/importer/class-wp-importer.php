<?php

namespace IfmImport;

use League\Csv\Reader;
use League\Csv\Statement;

class WpImporter
{
    public static $steps;
    public static $step = null;

    public $file_path;
    public $limit;
    public $offset;
    public static $custom_vars;
    public static $progress;


    public static $header;
    public static $records;
    public static $record;

    // Find all import methods in Actions.js 

    // maintain an array of ids to reference in later steps _on the same record_
    public static $ids = array();

    public function __construct()
    {
    }

    public function setup($file, $steps, $custom_vars, $offset = 0, $limit = -1)
    {
        if (!ini_get("auto_detect_line_endings")) {
            ini_set("auto_detect_line_endings", '1');
        }

        ini_set('memory_limit', '1024M');

        self::$custom_vars = $custom_vars;
        self::$steps = $steps;
        $this->readCSV($file, $limit, $offset);
    }

    public function readCSV($file_id, $limit, $offset)
    {
        $attached_file = get_attached_file($file_id);
        $csv = Reader::createFromPath($attached_file, 'r');
        // wp_delete_attachment($file_id);
        $csv->setHeaderOffset(0);
        self::$header = $csv->getHeader();

        if ($limit >= 0) {
            $stmt = (new Statement())
                ->offset($offset)
                ->limit($limit);
            self::$records = $stmt->process($csv);
        } else {
            self::$records = $csv->getRecords();
        }

        return self::$records;
    }

    public function run()
    {
        $progress = array();

        foreach (self::$records as $recordIndex => $record) {
            self::$record = $record;
            self::$ids = array();
            foreach (self::$steps as $stepIndex => $step) {
                // set wet_map to map containing values drawn from
                // csv records or posts/users created while processing record
                self::$step = $this->hydrate($step);

                // run function specified in step, e.g., 'get_user_by_email'
                $step_method = $step->action;

                xdebug_break();

                $step_output = $this->$step_method();
                self::$ids[$step->id] = $step_output;


                $success = false;

                if (intval($step_output)) {
                    $success = true;
                }

                // slightly repetitive code, refactor at later date
                $progress[$recordIndex][$stepIndex] = self::$step;
                $progress[$recordIndex][$stepIndex]["id"] = $step->id;
                $progress[$recordIndex][$stepIndex]["success"] = $success;

                $progress_line = array($recordIndex => array($stepIndex => array("id" => $step->id, "success" => $success, self::$step)));
                set_transient("ifm_progress", $progress_line, 36000);
            }
        }
        return json_encode(
            array(
                "complete" => true,
                "progress" => $progress,
                "err" => get_transient("ifm_error"),
            )
        );
    }


    public function hydrate($step)
    {
        // take the values from the php array and set the right hand side 
        // to a value from the CSV record, a generated value, or a string literal
        // goes 2-d right now
        $wet_step = array();

        foreach ($step->getMap as $mapRow) {
            if (!empty((array) $mapRow)) {
                $wet_step['get'][$mapRow->left] = $this->evaluate($mapRow);
            }
        }

        foreach ($step->setMap as $mapRow) {
            if (!empty((array) $mapRow)) {
                $wet_step['set'][$mapRow->left] = $this->evaluate($mapRow);
            }
        }

        return $wet_step;
    }

    public function evaluate($mapRow)
    {
        $value = $mapRow->right;
        $type = $mapRow->type;
        // '@' denotes a reference to a value previously set by the import 
        // process (user or post generated)
        if ("stepId" === $type) {

            // the name of id is the value passed
            $wet = array_key_exists($value, self::$ids) ? self::$ids[$value] : null;
        } elseif ("csvValue" === $type) {

            // get the csv value
            $wet = array_key_exists($value, self::$record) ? self::$record[$value] : null;
        } elseif ("customVar" === $type) {
            // buid the custom var
            $wet = VarBuilder::evaluate($value, self::$record, self::$custom_vars);
        } else {

            // everything else is a string literal
            $wet = $value;
        }
        return $wet;
    }

    public function create_user()
    {
        $user_id =  wp_insert_user(
            self::$step['set']
        );

        return $user_id;
    }

    public function create_post()
    {
        $post_id = wp_insert_post(
            self::$step['set']
        );

        return $post_id;
    }

    // public function get_post()
    // {
    //     xdebug_break();
    //     $key = key(self::$step['get']);
    //     $value = self::$step['get'][$key];
    //     switch ($key):
    //         case 'ID':
    //             return get_post($value)->ID;
    //             break;
    //         case 'post_title':
    //             $post = get_page_by_title($value);
    //             return $post->ID;
    //             break;
    //         default:
    //             return 0;
    //     endswitch;
    // }
    // public function get_user()
    // {
    //     return "hello user";
    // }

    public function update_post()
    {
        $post_id = 0;

        if (array_key_exists('ID', self::$step['get'])) {
            $post_id = wp_update_post(array_merge(self::$step['set'], self::$step['get']));
        }

        return $post_id;
    }

    public function update_user()
    {
        $user_id = 0;

        if (array_key_exists('ID', self::$step['get'])) {
            $user_id = wp_update_user(array_merge(self::$step['set'], self::$step['get']));
        }

        return $user_id;
    }

    public function update_user_meta()
    {
        $user_id = self::$step['user_id'];
        foreach (self::$step['map'] as $key => $value) {
            update_user_meta($user_id, $key, $value);
        }
    }

    public function add_post_terms()
    {
        $post_id = self::$step['get']['ID'];
        $taxonomy_obj = array();
        try {
            foreach (self::$step['set'] as $taxonomy => $value) {
                $taxonomy_obj[$value] = wp_set_object_terms($post_id, $value, $taxonomy, true);
            }
            return 1;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function add_post_meta()
    {
        $post_id = self::$step['get']['ID'];
        $meta_obj = array();
        try {
            foreach (self::$step['set'] as $meta_key => $meta_value) {
                $meta_obj[$meta_key] = add_post_meta($post_id, $meta_value, $meta_key, true);
            }
            return 1;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
    public function add_user_meta()
    {
        $user_id = self::$step['get']['ID'];
        $meta_obj = array();
        try {
            foreach (self::$step['set'] as $meta_value => $meta_key) {
                $meta_obj[$meta_key] = add_user_meta($user_id, $meta_value, $meta_key, true);
            }
            return 1;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    public function add_featured_image()
    {
        $post_id = self::$step['get']['ID'];
        $image_url = self::$step['set']['url'] ? self::$step['set']['url'] : "";

        if (isset(self::$step['set']['url']) && "" !== self::$step['set']['url']) {
            $image_url = self::$step['set']['url'];
        } elseif (isset(self::$step['set']['default_url']) && "" !== self::$step['set']['default_url']) {
            $image_url = self::$step['set']['default_url'];
        }

        if (isset(self::$step['set']['img_desc']) && "" !== self::$step['set']['img_desc']) {
            $image_desc = self::$step['set']['img_desc'];
        } else {
            $image_desc = basename($image_url);
        }

        $image_id = media_sideload_image($image_url, $post_id, $image_desc, 'id');


        // $upload_dir       = wp_upload_dir(); // Set upload folder
        // $image_data       = file_get_contents($image_url); // Get image data
        // $unique_file_name = wp_unique_filename($upload_dir['path'], $image_desc); // Generate unique name
        // $filename         = basename($unique_file_name); // Create image file name

        // // Check folder permission and define file location
        // if (wp_mkdir_p($upload_dir['path'])) {
        //     $file = $upload_dir['path'] . '/' . $filename;
        // } else {
        //     $file = $upload_dir['basedir'] . '/' . $filename;
        // }

        // // Create the image  file on the server
        // file_put_contents($file, $image_data);

        // // Check image file type
        // $wp_filetype = wp_check_filetype($filename, null);

        // // Set attachment data
        // $attachment = array(
        //     'post_mime_type' => $wp_filetype['type'],
        //     'post_title'     => sanitize_file_name($filename),
        //     'post_content'   => '',
        //     'post_status'    => 'inherit'
        // );

        // // Create the attachment
        // $attach_id = wp_insert_attachment($attachment, $image, $post_id);

        // // Define attachment metadata
        // $attach_data = wp_generate_attachment_metadata($attach_id, $file);

        // // Assign metadata to attachment
        // wp_update_attachment_metadata($attach_id, $attach_data);

        // And finally assign featured image to post
        return set_post_thumbnail($post_id, $image_id);
    }

    public function add_acf_meta()
    {
        $post_id = self::$step['post_id'];

        foreach (self::$step['map'] as $key => $value) :

            $field_object = get_field_object($key);
            $type = $field_object['type'];

            switch ($type):
                case 'text':
                case 'Wysiwyg Editor':
                case 'oEmbed':
                case 'user':
                    update_field($key, $value, $post_id);
                    break;
                case 'image':
                    $attachment_id = media_sideload_image($value, $post_id, "", "id");
                    update_field($key, $attachment_id, $post_id);
                    break;
                case 'gallery':
                    $gal_arr = array();
                    foreach (explode(",", $value) as $img_url) :
                        $gal_arr[] = media_sideload_image($value, $post_id, "", "id");
                    endforeach;
                    update_field($key, $gal_arr, $post_id);
                    break;
                case 'repeater':
                    break;
                default:
                    update_field($key, $value, $post_id);
            endswitch;
        endforeach;
    }
}
