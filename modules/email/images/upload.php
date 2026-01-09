<?php
/**
 * AbroadWorks Management System - Email Images Upload API
 * Secure image upload handler with comprehensive security controls
 *
 * @author ikinciadam@gmail.com
 */

define("AW_SYSTEM", true);
require_once "../../../includes/init.php";

header("Content-Type: application/json");

$response = ["success" => false, "message" => "", "data" => null];

// Auth check
if (!isset($_SESSION["user_id"])) {
    $response["message"] = "Unauthorized access";
    http_response_code(401);
    echo json_encode($response);
    exit;
}

if (!has_permission("timeworks_email_templates")) {
    $response["message"] = "Permission denied";
    http_response_code(403);
    echo json_encode($response);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $response["message"] = "Invalid request method";
    http_response_code(405);
    echo json_encode($response);
    exit;
}

// CSRF check
if (!isset($_POST["csrf_token"]) || !isset($_SESSION["email_images_csrf"]) || $_POST["csrf_token"] !== $_SESSION["email_images_csrf"]) {
    $response["message"] = "Invalid security token";
    http_response_code(403);
    echo json_encode($response);
    exit;
}

// Config
$config = [
    "max_file_size" => 750 * 1024,
    "allowed_extensions" => ["jpg", "jpeg", "png", "gif", "webp"],
    "allowed_mime_types" => ["image/jpeg", "image/png", "image/gif", "image/webp"],
    "upload_dir" => "../../../uploads/email_images/",
    "web_path" => "/uploads/email_images/"
];

// File check
if (!isset($_FILES["image"]) || $_FILES["image"]["error"] === UPLOAD_ERR_NO_FILE) {
    $response["message"] = "No file uploaded";
    http_response_code(400);
    echo json_encode($response);
    exit;
}

$file = $_FILES["image"];

if ($file["error"] !== UPLOAD_ERR_OK) {
    $error_messages = [
        UPLOAD_ERR_INI_SIZE => "File exceeds server maximum upload size",
        UPLOAD_ERR_FORM_SIZE => "File exceeds form maximum upload size",
        UPLOAD_ERR_PARTIAL => "File was only partially uploaded",
        UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder",
        UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
        UPLOAD_ERR_EXTENSION => "Upload stopped by PHP extension"
    ];
    $response["message"] = $error_messages[$file["error"]] ?? "Unknown upload error";
    http_response_code(400);
    echo json_encode($response);
    exit;
}

if ($file["size"] > $config["max_file_size"]) {
    $response["message"] = "File size exceeds maximum allowed (750KB)";
    http_response_code(400);
    echo json_encode($response);
    exit;
}

$original_name = $file["name"];
$extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

if (!in_array($extension, $config["allowed_extensions"])) {
    $response["message"] = "Invalid file type. Allowed: " . implode(", ", $config["allowed_extensions"]);
    http_response_code(400);
    echo json_encode($response);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$detected_mime = $finfo->file($file["tmp_name"]);

if (!in_array($detected_mime, $config["allowed_mime_types"])) {
    $response["message"] = "Invalid file content type";
    http_response_code(400);
    echo json_encode($response);
    exit;
}

$image_info = @getimagesize($file["tmp_name"]);
if ($image_info === false) {
    $response["message"] = "File is not a valid image";
    http_response_code(400);
    echo json_encode($response);
    exit;
}

// Sanitize filename
$name = pathinfo($original_name, PATHINFO_FILENAME);
$name = strtolower($name);
$turkish_chars = ["ı", "ğ", "ü", "ş", "ö", "ç", "İ", "Ğ", "Ü", "Ş", "Ö", "Ç"];
$english_chars = ["i", "g", "u", "s", "o", "c", "i", "g", "u", "s", "o", "c"];
$name = str_replace($turkish_chars, $english_chars, $name);
$name = preg_replace("/[^a-z0-9_-]/", "_", $name);
$name = preg_replace("/_+/", "_", $name);
$name = trim($name, "_");
if (empty($name)) $name = "image";
$name = substr($name, 0, 50);

$timestamp = date("Ymd_His");
$unique_id = substr(md5(uniqid(mt_rand(), true)), 0, 8);
$new_filename = "{$name}_{$timestamp}_{$unique_id}.{$extension}";

$upload_path = realpath(dirname(__FILE__) . "/" . $config["upload_dir"]);
if (!$upload_path) {
    @mkdir(dirname(__FILE__) . "/" . $config["upload_dir"], 0755, true);
    $upload_path = realpath(dirname(__FILE__) . "/" . $config["upload_dir"]);
}

if (!$upload_path || !is_writable($upload_path)) {
    $response["message"] = "Upload directory is not writable";
    http_response_code(500);
    echo json_encode($response);
    exit;
}

$destination = $upload_path . "/" . $new_filename;

// Process image with GD
$source_image = null;
switch ($detected_mime) {
    case "image/jpeg": $source_image = @imagecreatefromjpeg($file["tmp_name"]); break;
    case "image/png": $source_image = @imagecreatefrompng($file["tmp_name"]); break;
    case "image/gif": $source_image = @imagecreatefromgif($file["tmp_name"]); break;
    case "image/webp": $source_image = @imagecreatefromwebp($file["tmp_name"]); break;
}

if (!$source_image) {
    $response["message"] = "Failed to process image";
    http_response_code(400);
    echo json_encode($response);
    exit;
}

$width = imagesx($source_image);
$height = imagesy($source_image);

$save_success = false;
switch ($detected_mime) {
    case "image/jpeg": $save_success = imagejpeg($source_image, $destination, 90); break;
    case "image/png": imagesavealpha($source_image, true); $save_success = imagepng($source_image, $destination, 9); break;
    case "image/gif": $save_success = imagegif($source_image, $destination); break;
    case "image/webp": $save_success = imagewebp($source_image, $destination, 90); break;
}
imagedestroy($source_image);

if (!$save_success) {
    $response["message"] = "Failed to save image";
    http_response_code(500);
    echo json_encode($response);
    exit;
}

chmod($destination, 0644);
$final_size = filesize($destination);

// Database insert
try {
    $stmt = $db->prepare("INSERT INTO email_images (filename, original_name, mime_type, file_size, width, height, uploaded_by, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$new_filename, $original_name, $detected_mime, $final_size, $width, $height, $_SESSION["user_id"]]);
    
    $image_id = $db->lastInsertId();
    
    $protocol = (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] === "on") || (isset($_SERVER["HTTP_X_FORWARDED_PROTO"]) && $_SERVER["HTTP_X_FORWARDED_PROTO"] === "https") ? "https" : "http";
    $host = $_SERVER["HTTP_HOST"];
    $base_url = $protocol . "://" . $host;
    
    $response["success"] = true;
    $response["message"] = "Image uploaded successfully";
    $response["data"] = [
        "id" => $image_id,
        "filename" => $new_filename,
        "original_name" => $original_name,
        "url" => $base_url . $config["web_path"] . $new_filename,
        "relative_url" => $config["web_path"] . $new_filename,
        "size" => $final_size,
        "width" => $width,
        "height" => $height,
        "mime_type" => $detected_mime
    ];
} catch (PDOException $e) {
    @unlink($destination);
    error_log("Email image upload DB error: " . $e->getMessage());
    $response["message"] = "Failed to save image information";
    http_response_code(500);
}

echo json_encode($response);
