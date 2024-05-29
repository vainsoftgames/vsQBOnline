<?php
	use QuickBooksOnline\API\DataService\DataService;
	use QuickBooksOnline\API\Core\OAuth\OAuth2\OAuth2LoginHelper;
	use QuickBooksOnline\API\Facades\TimeActivity;
	use QuickBooksOnline\API\Facades\Purchase;

	class vsQBOnline {
		private $token_path = 'tokens.json';
		public $tokens;


		public function __construct(){
		}


		// Token Management
		public function getTokens(){
			if(!$this->tokens && file_exists($this->token_path)){
				$json = @json_decode(file_get_contents($this->token_path), true);
				if($json) $this->tokens = $json;
			}

			if($this->tokens){
				return [
					$this->tokens['access_token'],
					$this->tokens['refresh_token'],
					$this->tokens['expires']
				];
			}
			return false;
		}

		private function saveTokens(){
			if($this->tokens){
				return file_put_contents($this->token_path, json_encode($this->tokens, JSON_PRETTY_PRINT));
			}
			return false;
		}

		public function refreshTokens(){
			if(!$this->tokens) $this->getTokens();

			$dataService = DataService::Configure([
				'auth_mode' => 'oauth2',
				'ClientID' => CLIENT_ID,
				'ClientSecret' => CLIENT_SECRET,
				'RedirectURI' => REDIRECT_URI,
				'baseUrl' => 'development',
			]);
		
			$OAuth2LoginHelper = $dataService->getOAuth2LoginHelper();
		
			try {
				$newAccessToken = $OAuth2LoginHelper->refreshAccessTokenWithRefreshToken($this->tokens['refresh_token']);
				$dataService->updateOAuth2Token($newAccessToken);
		
				// Update the .env file with the new access and refresh tokens
				$access_token = $newAccessToken->getAccessToken();
				$refresh_token = $newAccessToken->getRefreshToken();

				$this->tokens = [
					'access_token'	=> $newAccessToken->getAccessToken(),
					'refresh_token'	=> $newAccessToken->getRefreshToken(),
					'expires'		=> (time() + 3600)
				];

				return $this->saveTokens();
			}
			catch (Exception $e) {
				error_log('Failed to refresh access token: '. $e->getMessage());
				return false;
			}
		}


		public function getDataService() {
			if (!$this->tokens || $this->tokens['expires'] < time()) {
				$this->refreshTokens();
			}
	
			return DataService::Configure([
				'auth_mode' => 'oauth2',
				'ClientID' => CLIENT_ID,
				'ClientSecret' => CLIENT_SECRET,
				'RedirectURI' => REDIRECT_URI,
				'baseUrl' => 'development',
				'accessTokenKey' => $this->tokens['access_token'],
				'refreshTokenKey' => $this->tokens['refresh_token'],
				'QBORealmID' => REALM_ID
			]);
		}

		// Clean up responses, only return selected fields instead of null values
		private function filterFields($data, $fields=false){
			if(!$fields) return $data;
			
			$fields = array_flip($fields); // Flip the array for faster lookups
			
			$results = array_map(function($item) use ($fields) {
				$entry = array_intersect_key((array)$item, $fields); // Cast item to array if necessary and filter keys
				return (object)$entry; // Convert back to object if needed
			}, $data);

			return $results;
		}


		// Employee Functions
		public function getEmployee($id, $fields=false){
			return $this->getEmployees($fields, $id);
		}

		public function getEmployees($fields = false, $ids=false) {
			$dataService = $this->getDataService();

			if(!$fields) $SEL = '*';
			else $SEL = implode(",", (is_array($fields) ? $fields : [$fields]));

			$query = "SELECT {$SEL} FROM Employee";

			if($ids) $query .= " WHERE Id IN ('". implode("','", (is_array($ids) ? $ids : [$ids])) .")'";

			$employees = $dataService->Query($query);

			if ($error = $dataService->getLastError()) {
				error_log("The Status code is: " . $error->getHttpStatusCode());
				error_log("The Helper message is: " . $error->getOAuthHelperError());
				error_log("The Response message is: " . $error->getResponseBody());
				return false;
			}

			return $this->filterFields($employees, $fields);
		}
		
		public function getCategories($fields = false) {
			$ds = $this->getDataService();

			if(!$fields) $SEL = '*';
			else $SEL = implode(",", (is_array($fields) ? $fields : [$fields]));
        
			$query = "SELECT {$SEL} FROM Account";
        
			$accounts = $ds->Query($query);

			if ($error = $ds->getLastError()) {
				error_log("The Status code is: " . $error->getHttpStatusCode());
				error_log("The Helper message is: " . $error->getOAuthHelperError());
				error_log("The Response message is: " . $error->getResponseBody());
				return false;
			}

			return $this->filterFields($accounts, $fields);
		}
		
		
		
		
		// Vendor Functions
		public function getVendors($fields = false){
			$ds = $this->getDataService();

			if(!$fields) $SEL = '*';
			else $SEL = implode(",", (is_array($fields) ? $fields : [$fields]));
			
			$query = "SELECT {$SEL} FROM Vendor";
			$vendors = $ds->Query($query);
			
			if($error = $ds->getLastError()){
				return false;
			}
			
			return $this->filterFields($vendors, $fields);
		}
		
		public function createLineItem($amount, $accountID, $desc=false){
			$key = 'AccountBasedExpenseLineDetail';

			$entry = [];
			$entry['Amount'] = $amount;
			$entry['DetailType'] = $key;
			$entry[$key] = [
				'AccountRef'	=> [
					'value'	=> $accountID
				]
			];

			if($desc) $entry[$key]['Description'] = $desc;

			return $entry;
		}
		
		public function addExpenseToVendor($accountID, $methodID, $vendorID, $date, $lines, $paymentType='Cash', $tags=false, $note=false){
			$ds = $this->getDataService();
			
			$date = date('Y-m-d', (is_numeric($date) ? $date : strtotime($date)));
			
			$payment = [
				"AccountRef"	=> [
					"value"			=> $accountID
				],
				"PaymentType"	=> $paymentType,
				"PaymentMethodRef" => [
					"value"			=> $methodID
				],
				"EntityRef"		=> [
					"value"			=> $vendorID,
					"type"			=> "Vendor"
				],
				"Line"			=> $lines,
				"TxnDate"		=> $date,
				"PrivateNote"	=> $note
			];
			
			$data = Purchase::create($payment);
			
			$result = $ds->Add($data);
			
			
			if($error = $ds->getLastError()){
				error_log("The Status code is: " . $error->getHttpStatusCode());
				error_log("The Helper message is: " . $error->getOAuthHelperError());
				error_log("The Response message is: " . $error->getResponseBody());
				return false;
			}
			else return $result;
		}
		public function addBillToVendor($vendorID, $date, $lines, $paymentType='Cash', $note=false){
			$ds = $this->getDataService();
			
			$bill = Bill::create([
				"VendorRef"	=> [
					"value"		=> $vendorID
				],
				"PaymentType"	=> $paymentType,
				"Line"	=> $lines,
				"TnxDate"	=> date('Y-m-d', (is_numeric($date) ? $date : strtotime($date))),
			]);
		}
	}
?>
