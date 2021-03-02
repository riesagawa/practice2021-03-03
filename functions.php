<?php 

function throwError($errors) {
	header('HTTP/1.0 422 Unprocessable Entity');
	echo json_encode($errors);
	exit();
}

function config($key) {
	$config = include dirname(__FILE__) . '/settings/config.php';
	$key = explode( '.', $key );
	for( $i = 0; $i < count($key); $i++ ) {
		$config = $config[$key[$i]];
	}
	return $config;
}

function sanitizeFormInputs($request){
	$sanitizedData = [];
	foreach ($request as $key => $value) {
		$sanitizedData[$key] = trim(stripslashes($value));
	}

	return $sanitizedData;
}

function validateFormInputs($request) {
	$config = config('contact_form');
	$errors = [];
	foreach ($request as $key => $value) {
		if( isset($config['validation'][$key]) ){
			$validations = explode('|', $config['validation'][$key]);
			if( !empty($validations) ) {
				$messages = [];
				foreach ($validations as $validation) {
					if( ! callValidation($validation, $value) ) {
						$messages[] = isset($config['validationMessages'][$validation]) ? $config['validationMessages'][$validation]: '';
					};
				}
				if( !empty($messages) ) {
						$errors[$key] = $messages;
				}
			}
		}
	}
	if( !empty($errors) ) {
		return $errors;
	}
	return false;
}

function callValidation($validation, $value){
	$function = explode(':', $validation);
	$functionName = 'validate' . ucfirst($function[0]);
	if( isset($function[1]) ) {
		return $functionName($value, $function[1]);
	}
	return $functionName($value);
}


function validateRequired($value){
	return ! empty($value);
}

function validateEmail($value){
	return preg_match('/^(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){255,})(?!(?:(?:\x22?\x5C[\x00-\x7E]\x22?)|(?:\x22?[^\x5C\x22]\x22?)){65,}@)(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22))(?:\.(?:(?:[\x21\x23-\x27\x2A\x2B\x2D\x2F-\x39\x3D\x3F\x5E-\x7E]+)|(?:\x22(?:[\x01-\x08\x0B\x0C\x0E-\x1F\x21\x23-\x5B\x5D-\x7F]|(?:\x5C[\x00-\x7F]))*\x22)))*@(?:(?:(?!.*[^.]{64,})(?:(?:(?:xn--)?[a-z0-9]+(?:-[a-z0-9]+)*\.){1,126}){1,}(?:(?:[a-z][a-z0-9]*)|(?:(?:xn--)[a-z0-9]+))(?:-[a-z0-9]+)*)|(?:\[(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){7})|(?:(?!(?:.*[a-f0-9][:\]]){7,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,5})?)))|(?:(?:IPv6:(?:(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){5}:)|(?:(?!(?:.*[a-f0-9]:){5,})(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3})?::(?:[a-f0-9]{1,4}(?::[a-f0-9]{1,4}){0,3}:)?)))?(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))(?:\.(?:(?:25[0-5])|(?:2[0-4][0-9])|(?:1[0-9]{2})|(?:[1-9]?[0-9]))){3}))\]))$/iD', $value);
}

function validateMin($value, $min){
	return  strlen($value) > $min; 
}

function formatTemplate($template, $data) {
	$template = preg_replace_callback('/\{\{([a-z]+)\}\}/U', function($match) use ($data) {
		return $data[$match[1]];
	}, $template);

	return preg_replace('/^\s+/m', '', $template);
}

function sendMessage($request) {
	extract($request);
	$template = config('contact_form.template');
	$message = formatTemplate($template, $request);

	// Email Headers
	$headers = "From: " . config('contact_form.from.name') . " <" . config('contact_form.from.email') . ">\r\n";
	$headers .= "Reply-To: ". $email . "\r\n";
	$headers .= "MIME-Version: 1.0\r\n";
	$headers .= "Content-Type: text/plain; charset=ISO-8859-1\r\n";
	$to = config('contact_form.to');
	ini_set("sendmail_from", $to); // Windows servers

	if ($subject == '') {
		$subject =  config('contact_form.defaultSubject');
	}
	
	if( mail($to, $subject, $message, $headers) ) {
		return true;
	};

	throwError(['server_error' => config('contact_form.serverError')]);

}