<?php
prado::using("Application.pages.herramientas.General");
class EnviarVehiculo {
    public function EnviarVehiculoManifiesto($intOrdDespacho) {
        $booResultados = TRUE;
        $objGeneral = new General();                                       
        $cliente = $objGeneral->CrearConexion();        
        $arDespacho = new DespachosRecord();
        $arDespacho = DespachosRecord::finder()->FindByPk($intOrdDespacho);        
        if($this->EnviarVehiculoWebServices($arDespacho->IdVehiculo, $cliente) == false){
            $booResultados = false;
        }  
        return $booResultados;
    }
    
    public function EnviarVehiculoWebServices($strVehiculo, $cliente){
        $boolResultadosEnvio = False;     
        $boolErroresDatos = FALSE;
        $arVehiculo = new VehiculosRecord();
        $arVehiculo = VehiculosRecord::finder()->with_Tenedor()->with_Propietario()->findByPk($strVehiculo);
        if($this->ValidarDatosVehiculo($arVehiculo) == true) {
            $strXmlVehiculo = array('' => $this->GenerarXMLVehiculo($arVehiculo));
            while ($boolResultadosEnvio == FALSE && $boolErroresDatos == FALSE) {
                $respuesta = "";
                try {                    
                    $respuesta = $cliente->__soapCall('AtenderMensajeRNDC', $strXmlVehiculo);
                    $cadena_xml = simplexml_load_string($respuesta);
                    if($cadena_xml->ErrorMSG != "") {
                        if(substr(strtoupper($cadena_xml->ErrorMSG),0,9) == "DUPLICADO") {
                            $boolResultadosEnvio = TRUE;       
                        } elseif(substr($cadena_xml->ErrorMSG, 0, 19) == "Error al abrir sesi" || substr($cadena_xml->ErrorMSG, 0, 23) == "Error al realizar conex") {
                            sleep(3);                                
                        } else {
                            General::InsertarErrorWS(2, "Vehiculos", $arVehiculo->IdPlaca, utf8_decode($cadena_xml->ErrorMSG));                            
                            $boolErroresDatos = TRUE;
                        }
                    }
                    if($cadena_xml->ingresoid) {
                        General::InsertarErrorWS(2, "Vehiculos", $arVehiculo->IdPlaca, utf8_decode($cadena_xml->ingresoid));                        
                        General::InsertarAprobacion("Vehiculos", $arVehiculo->IdPlaca, utf8_decode($cadena_xml->ingresoid));
                        $boolResultadosEnvio = true;
                    }                    
                } catch (Exception $e) {           
                    if(substr($e, 0, 19 ) == "SoapFault exception") {
                        sleep(3);                            
                    }
                    else { 
                        General::InsertarErrorWS(1, "General", "", "Error al enviar parametros" . $e);
                        $boolErroresDatos = TRUE;
                    }                                            
                }                    
            }
        }
        else {
            $boolResultadosEnvio = false; 
        }

        if($boolResultadosEnvio == true) {
            $this->ActualizarVehiculo($strVehiculo);
        }            
                      
        return $boolResultadosEnvio;
    }
    
    public function ValidarDatosVehiculo ($arVehiculo) {
        $intResultadoValidacion = TRUE;
        $strIdAseguradora = $arVehiculo->IdAseguradora . $this->calcularDV($arVehiculo->IdAseguradora);
        $arAseguradoras = new AseguradorasRecord();
        $arAseguradoras = AseguradorasRecord::finder()->FindByPk($strIdAseguradora);
        if(count($arAseguradoras) <= 0) {
            $intResultadoValidacion = FALSE;
            General::InsertarErrorWS(3, "Vehiculos", $arVehiculo->IdPlaca, "La aseguradora de vehiculo no existe en la base de datos del ministerio");
        }
        return $intResultadoValidacion;            
    }
    
    public function GenerarXMLVehiculo($arVehiculo) {
        $arConfiguracion = new ConfiguracionRecord();
        $arConfiguracion = ConfiguracionRecord::finder()->findByPk(1);        
        $strVehiculoXML = "";
        $dateFechaVenceSoat = substr($arVehiculo->VenceSoat, 8, 2) . "/" . substr($arVehiculo->VenceSoat, 5, 2) . "/" . substr($arVehiculo->VenceSoat, 0, 4);            
        if(count($arVehiculo) > 0) {
        $strVehiculoXML = "<?xml version='1.0' encoding='ISO-8859-1' ?>
                        <root>
                            <acceso>
                                <username>$arConfiguracion->UsuarioWS</username>
                                <password>$arConfiguracion->ClaveWS</password>
                            </acceso>
                            <solicitud>
                                <tipo>1</tipo>
                                <procesoid>12</procesoid>
                            </solicitud>
                            <variables>
                                <NUMNITEMPRESATRANSPORTE>$arConfiguracion->EmpresaWS</NUMNITEMPRESATRANSPORTE>
                                <NUMPLACA>" . $arVehiculo->IdPlaca  . "</NUMPLACA>
                                <CODCONFIGURACIONUNIDADCARGA>" . $arVehiculo->VehConfiguracion . "</CODCONFIGURACIONUNIDADCARGA>
                                <NUMEJES>" . $arVehiculo->NroEjes . "</NUMEJES>
                                <CODMARCAVEHICULOCARGA>1</CODMARCAVEHICULOCARGA>
                                <CODLINEAVEHICULOCARGA>373</CODLINEAVEHICULOCARGA>
                                <ANOFABRICACIONVEHICULOCARGA>" . $arVehiculo->Modelo . "</ANOFABRICACIONVEHICULOCARGA>
                                <CODTIPOCOMBUSTIBLE>1</CODTIPOCOMBUSTIBLE>
                                <PESOVEHICULOVACIO>" . $arVehiculo->PesoVacio . "</PESOVEHICULOVACIO>
                                <CODCOLORVEHICULOCARGA>" . $arVehiculo->IdColor . "</CODCOLORVEHICULOCARGA>
                                <CODTIPOCARROCERIA>" . $arVehiculo->IdCarroceria . "</CODTIPOCARROCERIA>
                                <CODTIPOIDPROPIETARIO>" . $arVehiculo->Propietario->TpDoc . "</CODTIPOIDPROPIETARIO>
                                <NUMIDPROPIETARIO>" . $arVehiculo->Propietario->IDTercero . "</NUMIDPROPIETARIO>
                                <CODTIPOIDTENEDOR>" . $arVehiculo->Tenedor->TpDoc . "</CODTIPOIDTENEDOR>
                                <NUMIDTENEDOR>" . $arVehiculo->Tenedor->IDTercero . "</NUMIDTENEDOR> 
                                <NUMSEGUROSOAT>" . $arVehiculo->Soat . "</NUMSEGUROSOAT> 
                                <FECHAVENCIMIENTOSOAT>" . $dateFechaVenceSoat . "</FECHAVENCIMIENTOSOAT>
                                <NUMNITASEGURADORASOAT>" . $arVehiculo->IdAseguradora . $this->calcularDV($arVehiculo->IdAseguradora) . "</NUMNITASEGURADORASOAT>
                                <CAPACIDADUNIDADCARGA>$arVehiculo->Capkilos</CAPACIDADUNIDADCARGA>
                                <UNIDADMEDIDACAPACIDAD>1</UNIDADMEDIDACAPACIDAD>
                            </variables>
                        </root>";             
        }              
        return $strVehiculoXML;
    }  
    
    public function ActualizarVehiculo($strVehiculo) {
        $arVehiculo = new VehiculosRecord();
        $arVehiculo = VehiculosRecord::finder()->findByPk($strVehiculo);
        $arVehiculo->ActualizadoWebServices = 1;
        $arVehiculo->save();
    }
    
    private function calcularDV($nit) {
        if (!is_numeric($nit)) {
            return false;
        }

        $arr = array(1 => 3, 4 => 17, 7 => 29, 10 => 43, 13 => 59, 2 => 7, 5 => 19,
            8 => 37, 11 => 47, 14 => 67, 3 => 13, 6 => 23, 9 => 41, 12 => 53, 15 => 71);
        $x = 0;
        $y = 0;
        $z = strlen($nit);
        $dv = '';

        for ($i = 0; $i < $z; $i++) {
            $y = substr($nit, $i, 1);
            $x += ($y * $arr[$z - $i]);
        }

        $y = $x % 11;

        if ($y > 1) {
            $dv = 11 - $y;
            return $dv;
        } else {
            $dv = $y;
            return $dv;
        }
    }    
}
?>