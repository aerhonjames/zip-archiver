<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Zip_archiver{

    protected $ci;
	protected $errors = [];
	protected $exclude;
	protected $file_name;

    public function __construct(){
        $this->ci =& get_instance();
        $this->ci->load->library(array('zip'));
		$this->ci->load->helper(array('download'));

		$this->exclude = [
		];
		
    }	
	
	/**
	 * filename method sets name for the zip file
	 * @param  string $name filename for zip compression
	 * @return value       returns name that is set
	 */
	function file_name($name=NULL){
		if(isset($name)) $this->file_name = $name;

		return $this;
	}	
	
	/**
	 * compress method compresses selected file/folder/directory into zip file
	 * @param  string $source   path to target file/folder to compress
	 * @param  string $destination location where zip file will be directed to after compression
	 * @param  array  $exclude     files that will not be included in compression
	 * @return boolean              true if folder is successfully compressed ti zip file
	 */
	function compress($source=NULL, $destination=NULL, $exclude=[]){
		if(!$source) $source = $this->get_current_folder();

		if(!is_dir($source)) $this->errors[] = 'Zip compress: Path is invalid.';
		if($destination AND !$this->check_and_create_folders($destination)) $this->errors[] = 'Zip Compress: Destination is invalid.';

		if(!$this->has_errors()){	

			if(is_array($exclude)) $this->exclude = array_merge($this->exclude, $exclude);
			else $this->exclude[] = $exclude;

			$directories = scandir($source);
			$directories = array_diff($directories, $this->exclude); // remove excluded files 

			$basepath_array = explode(DIRECTORY_SEPARATOR, rtrim(BASEPATH));
			$basepath_array = array_values(array_filter($basepath_array));
			unset($basepath_array[count($basepath_array) - 1]);

			// print_array($basepath_array, 1);
			$current_folder = end($basepath_array);

			$root_path = $current_folder.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $source.DIRECTORY_SEPARATOR); // reformat all directory separators to be rendered properly as path to exclude during read_dir section      
						
			foreach($directories as $directory){ //loop directories and check if is_dir or is_file
				$target = $source.'/'.$directory;
			

				if(is_dir($target)) $this->ci->zip->read_dir($target, FALSE, $root_path);
				elseif(is_file($target)) $this->ci->zip->read_file($target, TRUE);
			}

			$destination = sprintf('%1$s/%2$s.zip', $destination, (isset($this->file_name) ? $this->file_name : $this->generate_zip_name($source)));
			$this->ci->zip->archive($destination);

		}

	} 	
	
	/**
	 * method that uncompress selected zip file 
	 * @param  string  $folder_path    path to target file
	 * @param  string  $destination  location where zip file will be uncompressed
	 * @param  boolean $for_deletion set to false if zip file will be erased after uncompressing
	 * @return boolean                true if uncompression succeeds
	 */
	function uncompress($zip_file=NULL, $destination=NULL, $for_deletion=TRUE){
		if(!file_exists($zip_file) OR !is_file($zip_file)) $this->errors[] = 'Zip uncompress: Uncompress path is invalid';
		if($destination AND !$this->check_and_create_folders($destination)) $this->errors[] = 'Zip uncompress: Destination cannot create.';

		$this->check_zip_format($zip_file);

		if(!$this->has_errors()){
			if(!$destination){
				$root = explode('/', $zip_file, -1);
				$root = implode('/', $root);

				$destination = $root;
			}

			$zip = new ZipArchive;

			if($zip->open($zip_file) === TRUE){
			    $zip->extractTo($destination);
			    $zip->close();
			} 
			else $this->errors[] = 'Zip uncompress: File compression error';

			if($for_deletion) $this->delete_zip($zip_file);

			return ($this->has_errors()) ? FALSE : TRUE;
		}


		return FALSE;		

	}	
	
	/**
	 * download_zip method downloads zip file
	 * @param  string $folder zip file to be downloaded
	 * @return        
	 */
	function download_zip($zip){
		if(!file_exists($zip)) $this->errors[] = 'Zip download: Zip file does not exist';

		$this->check_zip_format($zip);

		if(!$this->has_errors()){
			force_download($zip, NULL);
		}		
	}	
	
	/**
	 * delete_zip method deletes selected file
	 * @param  string $folder file to  be deleted
	 * @return boolean       true if folder is deleted
	 */
	function delete_zip($zip_file=NULL){
		if(file_exists($zip_file)) $this->check_zip_format($zip_file);

		if(!$this->has_errors()){
			unlink($zip_file);
			return TRUE;
		}

		return FALSE;
	}
	
	//error methods
	
	/**
	 * error method catches errors in library
	 * @return Boolean true if there are errors
	 */
	function errors(){
		return $this->errors;
	}

	function has_errors(){
		if(count($this->errors)) return TRUE;
		return FALSE;
	}	
	
	// Helpers
	
	/**
	 * checks input if in zip format
	 * @param  string $item validates item if in zip file format
	 * @return boolean  true if item is zip file     
	 */
	protected function check_zip_format($item){
		if($item){
			$file_dir = explode('.', $item);
			$extension = strtolower(end($file_dir));

			if(!in_array($extension, ['zip'])) $this->errors[] = 'Zip format: Invalid file type';

			if(!$this->has_errors()) return TRUE;
		}

		return FALSE;
	}

	/**
	 * generate name for zip file  
	 * @param  string $source generate name from directory passed in source
	 * @return value         file name
	 */
	protected function generate_zip_name($source){
		$file_name = explode('/',$source);
		$file_name = end($file_name);

		return $file_name;
	}

	/**
	 * get_current_folder method dynamically formats site folder location
	 * @return value  current site directory
	 */
	protected function get_current_folder(){
		$basepath_array = explode(DIRECTORY_SEPARATOR, rtrim(BASEPATH));
		$basepath_array = array_values(array_filter($basepath_array));
		unset($basepath_array[count($basepath_array) - 1]);
		$current_folder = '../'.end($basepath_array);

		return $current_folder;
	}

	/**
	 * check_and_create_folders method validates destination path if folder already exists and creates new folder if not
	 * @param  string $destination path to validate/create folder
	 * @return boolean              true if folder is created
	 */
	protected function check_and_create_folders($destination=NULL){
		if($destination AND !is_dir($destination)) {
			$path_arr = explode('/', $destination);
			foreach ($path_arr as $segment) {
				$segment_path[] = $segment;
				$target = join('/', $segment_path);
				if(!is_dir($target)) mkdir($target);
			}
		}

		if(is_dir($destination)) return TRUE;

		return FALSE;
	}
}