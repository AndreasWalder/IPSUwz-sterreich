<?
	class IPSUwzOesterreich extends IPSModule
	{
		private $imagePath;
		
		public function __construct($InstanceID)
		{
			//Never delete this line!
			parent::__construct($InstanceID);
			
			//You can add custom code below.
			$this->imagePath = "media/radar".$InstanceID.".png";
		}
		
		public function Create()
		{
			//Never delete this line!
			parent::Create();
			
			$this->RegisterPropertyString("area", "AT");
			$this->RegisterPropertyString("option", "RE");
			$this->RegisterPropertyInteger("homeX", 420);
			$this->RegisterPropertyInteger("homeY", 352);
			$this->RegisterPropertyInteger("homeRadius", 10);
			$this->RegisterPropertyInteger("Interval", 300);
			
			$this->RegisterTimer("UpdateTimer", 300 * 1000, 'UWZ_RequestInfo($_IPS[\'TARGET\']);');
		}
	
		public function ApplyChanges()
		{
			//Never delete this line!
			parent::ApplyChanges();
			
			$this->RegisterVariableInteger("RainValue", "Regenwert");
			$Interval = $this->ReadPropertyInteger("Interval");
			
			$this->SetTimerInterval("UpdateTimer", $Interval * 1000);
		}

		/**
		* This function will be available automatically after the module is imported with the module control.
		*/
		public function RequestInfo()
		{
		
			$imagePath = IPS_GetKernelDir() . $this->imagePath;
			$area = $this->ReadPropertyString("area");
			$option = $this->ReadPropertyString("option");
			$homeX = $this->ReadPropertyInteger("homeX");
			$homeY = $this->ReadPropertyInteger("homeY");
			$homeRadius = $this->ReadPropertyInteger("homeRadius");
					
			//Download picture
			$opts = array(
			'http'=>array(
				'method'=>"GET",
				'max_redirects'=>1,
				'header'=>"User-Agent: "."Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36"
			)
			);
			$context = stream_context_create($opts);

			if ($option == "RE") {
			  $remoteImage = "https://uwz.at/data/previews/AT_warning_today_rain_desktop.png";
			}
			
			if ($option == "GE") {
			  $remoteImage = "https://uwz.at/data/previews/AT_warning_today_thunderstorm_desktop.png";
			}
			
     		$data = @file_get_contents($remoteImage, false, $context);

			$this->SendDebug($http_response_header[0], $remoteImage, 0);
			
			if((strpos($http_response_header[0], "200") === false)) {
				echo $http_response_header[0]." ".$data;
				return;
			}

			file_put_contents($imagePath, $data);
			
			$mid = $this->RegisterMediaImage("RadarImage", "Radarbild", $this->imagePath);
			
			//Bild aktualisiern lassen in IP-Symcon
			IPS_SendMediaEvent($mid);
			
			//Radarbild auswerten
			$im = imagecreatefrompng($imagePath);
			imageAlphaBlending($im, false); 
            imageSaveAlpha($im, true); 

			$warnung[4] = imagecolorresolve  ($im, 175, 0, 100);  // dunkel rot
			$warnung[3] = imagecolorresolve  ($im, 255, 255, 0);  // rot
			$warnung[2] = imagecolorresolve  ($im, 250,  150, 0); // orange
			$warnung[1] = imagecolorresolve  ($im, 255,  255, 0); // gelb

			//Pixel durchgehen
			$rainValue = 0;
			for($x=$homeX-$homeRadius; $x<=$homeX+$homeRadius; $x++) {
				for($y=$homeY-$homeRadius; $y<=$homeY+$homeRadius; $y++) {
					$found = array_search(imagecolorat($im, $x, $y), $warnung);
						if(!($found === FALSE)) {
							$rainValue+=$found;
						}
				}
			}

			SetValue($this->GetIDForIdent("RainValue"), $rainValue);

			// Bereich zeichnen
			$rot = ImageColorAllocate ($im, 255, 0, 0);
			imagerectangle($im, $homeX-$homeRadius, $homeY-$homeRadius, $homeX+$homeRadius, $homeY+$homeRadius, $rot);
			imagesetpixel($im, $homeX, $homeY, $rot);
			
			// Zum Einstellen aktivieren!!
			imagepng($im, $imagePath); 
		}
		
		private function RegisterMediaImage($Ident, $Name, $Path) {	
			//search for already available media with proper ident
			$mid = @IPS_GetObjectIDByIdent($Ident, $this->InstanceID);	
			//properly update mediaID
			if($mid === false)
				$mid = 0;
			//we need to create one
			if($mid == 0)
			{
				$mid = IPS_CreateMedia(1);
				
				//configure it
				IPS_SetParent($mid, $this->InstanceID);
				IPS_SetIdent($mid, $Ident);
				IPS_SetName($mid, $Name);
				//IPS_SetReadOnly($mid, true);
			}
			//update path if needed
			if(IPS_GetMedia($mid)['MediaFile'] != $Path) {
                IPS_SetMediaFile($mid, $Path, false);
			}
            return $mid;
		}	
	}		
?>
