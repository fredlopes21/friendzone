<?php
include("includes/header.php");

$profile_id = $user->getUsername();
$imgSrc = "";
$result_path = "";
$msg = "";

/***********************************************************
	0 - Remove The Temp image if it exists
 ***********************************************************/
if (!isset($_POST['x']) && !isset($_FILES['image']['name'])) {
	//Delete users temp image
	$temppath = 'assets/img/profile-pics/' . $profile_id . '_temp.jpeg';
	if (file_exists($temppath)) {
		@unlink($temppath);
	}
}


if (isset($_FILES['image']['name'])) {
	/***********************************************************
	1 - Upload Original Image To Server
	 ***********************************************************/
	//Get Name | Size | Temp Location		    
	$ImageName = $_FILES['image']['name'];
	$ImageSize = $_FILES['image']['size'];
	$ImageTempName = $_FILES['image']['tmp_name'];
	//Get File Ext   
	$ImageType = @explode('/', $_FILES['image']['type']);
	$type = $ImageType[1]; //file type	
	//Set Upload directory    
	$uploaddir = $_SERVER['DOCUMENT_ROOT'] . '/friendzone/dist/assets/img/profile-pics';
	//Set File name	
	$file_temp_name = $profile_id . '_original.' . md5(time()) . 'n' . $type; //the temp file name
	$fullpath = $uploaddir . "/" . $file_temp_name; // the temp file path
	$file_name = $profile_id . '_temp.jpeg'; //$profile_id.'_temp.'.$type; // for the final resized image
	$fullpath_2 = $uploaddir . "/" . $file_name; //for the final resized image
	//Move the file to correct location
	$move = move_uploaded_file($ImageTempName, $fullpath);
	chmod($fullpath, 0777);
	//Check for valid uplaod
	if (!$move) {
		die('File didnt upload');
	} else {
		$imgSrc = "assets/img/profile-pics/" . $file_name; // the image to display in crop area
		$msg = "Upload Complete!";  	//message to page
		$src = $file_name;	 		//the file name to post from cropping form to the resize		
	}

	/***********************************************************
	2  - Resize The Image To Fit In Cropping Area
	 ***********************************************************/
	//get the uploaded image size	
	clearstatcache();
	$original_size = getimagesize($fullpath);
	$original_width = $original_size[0];
	$original_height = $original_size[1];
	// Specify The new size
	$main_width = 500; // set the width of the image
	$main_height = $original_height / ($original_width / $main_width);	// this sets the height in ratio									
	//create new image using correct php func			
	if ($_FILES["image"]["type"] == "image/gif") {
		$src2 = imagecreatefromgif($fullpath);
	} elseif ($_FILES["image"]["type"] == "image/jpeg" || $_FILES["image"]["type"] == "image/pjpeg") {
		$src2 = imagecreatefromjpeg($fullpath);
	} elseif ($_FILES["image"]["type"] == "image/png") {
		$src2 = imagecreatefrompng($fullpath);
	} else {
		$msg .= "There was an error uploading the file. Please upload a .jpg, .gif or .png file. <br />";
	}
	//create the new resized image
	$main = imagecreatetruecolor($main_width, $main_height);
	imagecopyresampled($main, $src2, 0, 0, 0, 0, $main_width, $main_height, $original_width, $original_height);
	//upload new version
	$main_temp = $fullpath_2;
	imagejpeg($main, $main_temp, 90);
	chmod($main_temp, 0777);
	//free up memory
	imagedestroy($src2);
	imagedestroy($main);
	//imagedestroy($fullpath);
	@unlink($fullpath); // delete the original upload					

} //ADD Image 	

/***********************************************************
	3- Cropping & Converting The Image To Jpg
 ***********************************************************/
if (isset($_POST['x'])) {

	//the file type posted
	$type = $_POST['type'];
	//the image src
	$src = 'assets/img/profile-pics/' . $_POST['src'];
	$finalname = $profile_id . md5(time());

	if ($type == 'jpg' || $type == 'jpeg' || $type == 'JPG' || $type == 'JPEG') {

		//the target dimensions 150x150
		$targ_w = $targ_h = 150;
		//quality of the output
		$jpeg_quality = 90;
		//create a cropped copy of the image
		$img_r = imagecreatefromjpeg($src);
		$dst_r = imagecreatetruecolor($targ_w, $targ_h);
		imagecopyresampled(
			$dst_r,
			$img_r,
			0,
			0,
			$_POST['x'],
			$_POST['y'],
			$targ_w,
			$targ_h,
			$_POST['w'],
			$_POST['h']
		);
		//save the new cropped version
		imagejpeg($dst_r, "assets/img/profile-pics/" . $finalname . "n.jpeg", 90);
	} else if ($type == 'png' || $type == 'PNG') {

		//the target dimensions 150x150
		$targ_w = $targ_h = 150;
		//quality of the output
		$jpeg_quality = 90;
		//create a cropped copy of the image
		$img_r = imagecreatefrompng($src);
		$dst_r = imagecreatetruecolor($targ_w, $targ_h);
		imagecopyresampled(
			$dst_r,
			$img_r,
			0,
			0,
			$_POST['x'],
			$_POST['y'],
			$targ_w,
			$targ_h,
			$_POST['w'],
			$_POST['h']
		);
		//save the new cropped version
		imagejpeg($dst_r, "assets/img/profile-pics/" . $finalname . "n.jpeg", 90);
	} else if ($type == 'gif' || $type == 'GIF') {

		//the target dimensions 150x150
		$targ_w = $targ_h = 150;
		//quality of the output
		$jpeg_quality = 90;
		//create a cropped copy of the image
		$img_r = imagecreatefromgif($src);
		$dst_r = imagecreatetruecolor($targ_w, $targ_h);
		imagecopyresampled(
			$dst_r,
			$img_r,
			0,
			0,
			$_POST['x'],
			$_POST['y'],
			$targ_w,
			$targ_h,
			$_POST['w'],
			$_POST['h']
		);
		//save the new cropped version
		imagejpeg($dst_r, "assets/img/profile-pics/" . $finalname . "n.jpeg", 90);
	}
	//free up memory
	imagedestroy($img_r); // free up memory
	imagedestroy($dst_r); //free up memory
	@unlink($src); // delete the original upload					

	//return cropped image to page	
	$result_path = "assets/img/profile-pics/" . $finalname . "n.jpeg";

	//Insert image into database
	$insert_pic_query = $con->prepare("UPDATE users SET profile_pic = :resultPath WHERE username= :un");
	$insert_pic_query->execute([':resultPath' => $result_path, ':un' => $userLoggedIn]);
	header("Location: " . $userLoggedIn);
} // post x
?>
<div id="Overlay" style=" width:100%; height:100%; border:0px #990000 solid; position:absolute; top:0px; left:0px; z-index:2000; display:none;"></div>

<div id="upload" class="container">

	<div class="card">
		<div class="card-header">
			<h1 class="card-title">Upload a Profile Picture</h1>
		</div>
		<div class="card-body">

			<form action="upload.php" method="post" enctype="multipart/form-data">
				<input type="file" id="image" name="image" /><br /><br />
				<input type="submit" value="Submit" />
			</form><br /><br />

		</div>
	</div>



	<?php
	if ($imgSrc) { //if an image has been uploaded display cropping area
	?>
		<script>
			$('#Overlay').show();
			$('.card').hide();
		</script>
		<div id="CroppingContainer" style="width:800px; max-height:600px; background-color:#FFF; position:relative; overflow:hidden; border:1px #ccc solid; z-index:2001; padding-bottom:0px;">

			<div id="CroppingArea" style="width:500px; max-height:400px; position:relative; overflow:hidden; margin:40px 0px 40px 40px; border:2px #666 solid; float:left;">
				<img src="<?= $imgSrc ?>" border="0" id="jcrop_target" style="border:0px #990000 solid; position:relative; margin:0px 0px 0px 0px; padding:0px; " />
			</div>

			<div id="InfoArea" style="width:180px; position:relative; overflow:hidden; margin:40px 0px 0px 40px; border:0px #666 solid; float:left;">

				<h3>Crop Profile Image</h3>
				<p>
					Crop / resize your uploaded profile image.
					Once you are happy with your profile image then please click save.
				</p>

			</div>

			<br />

			<div id="CropImageForm" style="width:100px; height:30px; float:left; margin:10px 0px 0px 40px;">
				<form action="upload.php" method="post" onsubmit="return checkCoords();">
					<input type="hidden" id="x" name="x" />
					<input type="hidden" id="y" name="y" />
					<input type="hidden" id="w" name="w" />
					<input type="hidden" id="h" name="h" />
					<input type="hidden" value="jpeg" name="type" /> <?php // $type 
																														?>
					<input type="hidden" value="<?= $src ?>" name="src" />
					<input type="submit" value="Save" class="btn-block" />
				</form>
			</div>

			<div id="CropImageForm2" style="width:100px; height:30px; float:left; margin:10px 0px 0px 40px;">
				<form action="upload.php" method="post" onsubmit="return cancelCrop();">
					<input type="submit" value="Cancel Crop" class="btn-block" />
				</form>
			</div>

		</div><!-- CroppingContainer -->
	<?php
	} ?>
</div>





<?php if ($result_path) {
?>

	<img src="<?= $result_path ?>" style="position:relative; margin:10px auto; width:150px; height:150px;" />

<?php } ?>


<br /><br />