<?php
prado::using("Application.pages.herramientas.General");
class EnviarTerceros {
    public function EnviarTercerosManifiesto($intOrdDespacho) {
        $objGeneral = new General();        
        $booResultados = TRUE;
        $arrTercero = array();
        $arDespacho = new DespachosRecord();
        $arDespacho = DespachosRecord::finder()->with_CiudadOrigen()->with_CiudadDestino()->FindByPk($intOrdDespacho);
        $arVehiculo = new VehiculosRecord();
        $arVehiculo = VehiculosRecord::finder()->findByPk($arDespacho->IdVehiculo);
        $arrTercero[] =  $arVehiculo->IdTenedor;
        $arrTercero[] =  $arVehiculo->IdPropietario;
        $arrTercero[] =  $arVehiculo->IdAseguradora;
        $arrTercero[] =  $arDespacho->IdConductor;
        $arTercero = new TercerosRecord();
        $arTercero = TercerosRecord::finder()->FindByPk($arDespacho->IdConductor);
        if(count($arTercero) > 0) {
            $arTercero->ActualizadoWebServices = 0;
            $arTercero->save();        
        }        

        $cliente = $objGeneral->CrearConexion();
        //Procesar array tercero
        foreach ($arrTercero as $arrTercero) {
            if($this->EnviarTerceroWebServices($arrTercero, $cliente) == false){
                $booResultados = false;
            }
        }
        if($this->EnviarDestinatarioWebServices($cliente, $arDespacho) == false){
            $booResultados = false;
        }     
        if($this->EnviarRemitenteWebServices($cliente, $arDespacho) == false){
            $booResultados = false;
        }         
        return $booResultados;
    }

    public function EnviarTerceroWebServices($intTercero, $cliente){
        
        $boolResultadosEnvio = False;
        $boolErroresDatos = FALSE;
        $arTercero = new TercerosRecord();
        $arTercero = TercerosRecord::finder()->with_Ciudad()->FindByPk($intTercero);
        if(count($arTercero) > 0) {
            if($arTercero->ActualizadoWebServices == 1) {
                $boolResultadosEnvio = true;
            }
            else {
                if($this->ValidarDatosTercero($arTercero) == true) {                                                    
                    $strXmlTercero = array('' => $this->GenerarXMLTercero($arTercero));                
                    while ($boolResultadosEnvio == FALSE && $boolErroresDatos == FALSE) {
                        $respuesta = "";
                        try {
                            $respuesta = $cliente->__soapCall('AtenderMensajeRNDC', $strXmlTercero);
                            $cadena_xml = simplexml_load_string($respuesta);
                            if($cadena_xml->ErrorMSG != "") {
                                if(substr(strtoupper($cadena_xml->ErrorMSG),0,9) == "DUPLICADO") {
                                    $boolResultadosEnvio = TRUE;
                                } elseif(substr($cadena_xml->ErrorMSG, 0, 19) == "Error al abrir sesi" || substr($cadena_xml->ErrorMSG, 0, 23) == "Error al realizar conex") {
                                    sleep(3);                                
                                }
                                else {
                                    General::InsertarErrorWS(2, "Personas", $arTercero->IDTercero, utf8_decode($cadena_xml->ErrorMSG));
                                    $boolErroresDatos = TRUE;
                                }                            
                            }
                            if($cadena_xml->ingresoid) {
                                General::InsertarErrorWS(2, "Personas", $arTercero->IDTercero, utf8_decode($cadena_xml->ingresoid));
                                General::InsertarAprobacion("Personas", $arTercero->IDTercero, utf8_decode($cadena_xml->ingresoid));
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
                else
                    $boolResultadosEnvio = false;

                if($boolResultadosEnvio == true) {
                    $this->ActualizarTercero($intTercero);
                }
            }            
        }
        else {
            General::InsertarErrorWS(2, "Personas", $intTercero, "El conductor con esta identificacion no esta creado en terceros, debe crearlo");
        }

        return $boolResultadosEnvio;
    }

    public function ValidarDatosTercero ($arTercero) {
        $intResultadoValidacion = TRUE;
        if($arTercero->Telefono != "") {
            if(strlen($arTercero->Telefono) != 7) {
                $intResultadoValidacion = FALSE;
                General::InsertarErrorWS(3, "Personas", $arTercero->IDTercero, "El numero de telefono debe ser de 7 digitos");
            }
        }
        if($arTercero->Telefono == "" && $arTercero->TpDoc == "N") {
            $intResultadoValidacion = FALSE;
            General::InsertarErrorWS(3, "Personas", $arTercero->IDTercero, "Las empresas deben tener un numero de telefono");
        }
        if($arTercero->Celular != "") {
            if(strlen($arTercero->Celular) != 10) {
                $intResultadoValidacion = FALSE;
                General::InsertarErrorWS(3, "Personas", $arTercero->IDTercero, "El numero de celular debe ser de 10 digitos");
            }
        }
        if($arTercero->Telefono == "" && $arTercero->Celular == "") {
            $intResultadoValidacion = FALSE;
            General::InsertarErrorWS(3, "Personas", $arTercero->IDTercero, "El tercero debe tener celular o telefono");
        }
        $strDireccion = utf8_decode($arTercero->Direccion);
        $boolEnie = strpos($strDireccion, "ñ");
        $boolNumSimbolo = strpos($strDireccion, "°");
        $boolNum = strpos($strDireccion, "#");
        if($boolEnie === true || $boolNumSimbolo === true || $boolNum === true) {
            $intResultadoValidacion = FALSE;
            General::InsertarErrorWS(3, "Personas", $arTercero->IDTercero, "La direccion del tercero no puede contener caracteres especiales");            
        }
        return $intResultadoValidacion;
    }

    public function GenerarXMLTercero($arTercero) {
        $arConfiguracion = new ConfiguracionRecord();
        $arConfiguracion = ConfiguracionRecord::finder()->findByPk(1);
        $strTerceroXML = "";
        if(count($arTercero) > 0) {
            $arConductor = new ConductoresRecord();
            $arConductor = ConductoresRecord::finder()->FindByPk($arTercero->IDTercero);
            $strTerceroXML = "<?xml version='1.0' encoding='ISO-8859-1' ?>
                            <root>
                                <acceso>
                                    <username>$arConfiguracion->UsuarioWS</username>
                                    <password>$arConfiguracion->ClaveWS</password>
                                </acceso>
                                <solicitud>
                                    <tipo>1</tipo>
                                    <procesoid>11</procesoid>
                                </solicitud>
                                <variables>
                                    <NUMNITEMPRESATRANSPORTE>$arConfiguracion->EmpresaWS</NUMNITEMPRESATRANSPORTE>
                                    <CODTIPOIDTERCERO>". $arTercero->TpDoc ."</CODTIPOIDTERCERO>
                                    <NUMIDTERCERO>" . $arTercero->IDTercero . "</NUMIDTERCERO>
                                    <NOMIDTERCERO>" . utf8_decode($arTercero->Nombre) . "</NOMIDTERCERO>";
                                    if($arTercero->TpDoc == "C") {
                                        $strTerceroXML .= "<PRIMERAPELLIDOIDTERCERO>" . utf8_decode($arTercero->Apellido1) . "</PRIMERAPELLIDOIDTERCERO>
                                                           <SEGUNDOAPELLIDOIDTERCERO>" . utf8_decode($arTercero->Apellido2) . "</SEGUNDOAPELLIDOIDTERCERO>";
                                    }
                                    $strTerceroXML .= "<CODSEDETERCERO>1</CODSEDETERCERO>";
                                    $strTerceroXML .= "<NOMSEDETERCERO>PRINCIPAL</NOMSEDETERCERO>";
                                    if($arTercero->Telefono != "") {
                                        $strTerceroXML .= "<NUMTELEFONOCONTACTO>" . $arTercero->Telefono . "</NUMTELEFONOCONTACTO>";
                                    }
                                    if($arTercero->Celular != "" && $arTercero->TpDoc == "C") {
                                        $strTerceroXML .= "<NUMCELULARPERSONA>" . $arTercero->Celular . "</NUMCELULARPERSONA>";
                                    }
                                    $strTerceroXML .= "
                                    <NOMENCLATURADIRECCION>" . utf8_decode($arTercero->Direccion) . "</NOMENCLATURADIRECCION>
                                    <CODMUNICIPIORNDC>" . $arTercero->Ciudad->CodigoDivision . "</CODMUNICIPIORNDC>";
                                    if(count($arConductor) > 0 && $arTercero->TpDoc =="C") {
                                        $dateFechaVenceLic = substr($arConductor->FhVenceLic, 8, 2) . "/" . substr($arConductor->FhVenceLic, 5, 2) . "/" . substr($arConductor->FhVenceLic, 0, 4);
                                        $strTerceroXML .= "
                                        <CODCATEGORIALICENCIACONDUCCION>" . $arConductor->Categoria . "</CODCATEGORIALICENCIACONDUCCION>
                                        <NUMLICENCIACONDUCCION>" . $arConductor->LicenciaConductor . "</NUMLICENCIACONDUCCION>
                                        <FECHAVENCIMIENTOLICENCIA>" . $dateFechaVenceLic . "</FECHAVENCIMIENTOLICENCIA>";
                                    }
                                    $strTerceroXML .= "</variables>
                            </root>";
        }

        return $strTerceroXML;
    }

    public function ActualizarTercero($intTercero) {
        $arTercero = new TercerosRecord();
        $arTercero = TercerosRecord::finder()->FindByPk($intTercero);
        $arTercero->ActualizadoWebServices = 1;
        $arTercero->save();
    }
    
    public function EnviarDestinatarioWebServices($cliente, $arDespacho){
        
        $boolResultadosEnvio = False;
        $boolErroresDatos = FALSE;     
        
        $strXmlTercero = array('' => $this->GenerarXMLDestinatario($arDespacho));                
        while ($boolResultadosEnvio == FALSE && $boolErroresDatos == FALSE) {
            $respuesta = "";
            try {
                $respuesta = $cliente->__soapCall('AtenderMensajeRNDC', $strXmlTercero);
                $cadena_xml = simplexml_load_string($respuesta);
                if($cadena_xml->ErrorMSG != "") {
                    if(substr(strtoupper($cadena_xml->ErrorMSG),0,9) == "DUPLICADO") {
                        $boolResultadosEnvio = TRUE;
                    } elseif(substr($cadena_xml->ErrorMSG, 0, 19) == "Error al abrir sesi" || substr($cadena_xml->ErrorMSG, 0, 23) == "Error al realizar conex") {
                        sleep(3);                                
                    }
                    else {
                        General::InsertarErrorWS(2, "Destinatario", $arDespacho->IdManifiesto, utf8_decode($cadena_xml->ErrorMSG));
                        $boolErroresDatos = TRUE;
                    }                            
                }
                if($cadena_xml->ingresoid) {
                    General::InsertarErrorWS(2, "Destinatario", $arDespacho->IdManifiesto, utf8_decode($cadena_xml->ingresoid));
                    General::InsertarAprobacion("Destinatario", $arDespacho->IdManifiesto, utf8_decode($cadena_xml->ingresoid));
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
        return $boolResultadosEnvio;
    }
    
    public function GenerarXMLDestinatario($arDespacho) {
        $arConfiguracion = new ConfiguracionRecord();
        $arConfiguracion = ConfiguracionRecord::finder()->findByPk(1);
        $strIdDestinatario = "10000".$arDespacho->IdManifiesto;        
        $strTerceroXML = "<?xml version='1.0' encoding='ISO-8859-1' ?>
                        <root>
                            <acceso>
                                <username>$arConfiguracion->UsuarioWS</username>
                                <password>$arConfiguracion->ClaveWS</password>
                            </acceso>
                            <solicitud>
                                <tipo>1</tipo>
                                <procesoid>11</procesoid>
                            </solicitud>
                            <variables>
                                <NUMNITEMPRESATRANSPORTE>$arConfiguracion->EmpresaWS</NUMNITEMPRESATRANSPORTE>
                                <CODTIPOIDTERCERO>C</CODTIPOIDTERCERO>
                                <NUMIDTERCERO>$strIdDestinatario</NUMIDTERCERO>
                                <NOMIDTERCERO>DESTINATARIO</NOMIDTERCERO>                                   
                                <PRIMERAPELLIDOIDTERCERO>VARIOS</PRIMERAPELLIDOIDTERCERO>
                                <SEGUNDOAPELLIDOIDTERCERO>VARIOS</SEGUNDOAPELLIDOIDTERCERO>                                   
                                <CODSEDETERCERO>1</CODSEDETERCERO>
                                <NOMSEDETERCERO>PRINCIPAL</NOMSEDETERCERO>                                    
                                <NUMTELEFONOCONTACTO>3333333</NUMTELEFONOCONTACTO>
                                <NOMENCLATURADIRECCION>CALLE PRINCIPAL</NOMENCLATURADIRECCION>
                                <CODMUNICIPIORNDC>" . $arDespacho->CiudadDestino->CodMinTrans . "</CODMUNICIPIORNDC>
                            </variables>
                        </root>";        
        return $strTerceroXML;
    }    
    
    public function EnviarRemitenteWebServices($cliente, $arDespacho){
        
        $boolResultadosEnvio = False;
        $boolErroresDatos = FALSE;     
        
        $strXmlTercero = array('' => $this->GenerarXMLRemitente($arDespacho));                
        while ($boolResultadosEnvio == FALSE && $boolErroresDatos == FALSE) {
            $respuesta = "";
            try {
                $respuesta = $cliente->__soapCall('AtenderMensajeRNDC', $strXmlTercero);
                $cadena_xml = simplexml_load_string($respuesta);
                if($cadena_xml->ErrorMSG != "") {
                    if(substr(strtoupper($cadena_xml->ErrorMSG),0,9) == "DUPLICADO") {
                        $boolResultadosEnvio = TRUE;
                    } elseif(substr($cadena_xml->ErrorMSG, 0, 19) == "Error al abrir sesi" || substr($cadena_xml->ErrorMSG, 0, 23) == "Error al realizar conex") {
                        sleep(3);                                
                    }
                    else {
                        General::InsertarErrorWS(2, "Remitente", $arDespacho->IdManifiesto, utf8_decode($cadena_xml->ErrorMSG));
                        $boolErroresDatos = TRUE;
                    }                            
                }
                if($cadena_xml->ingresoid) {
                    General::InsertarErrorWS(2, "Remitente", $arDespacho->IdManifiesto, utf8_decode($cadena_xml->ingresoid));
                    General::InsertarAprobacion("Remitente", $arDespacho->IdManifiesto, utf8_decode($cadena_xml->ingresoid));
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
        return $boolResultadosEnvio;
    }
    
    public function GenerarXMLRemitente($arDespacho) {
        $arConfiguracion = new ConfiguracionRecord();
        $arConfiguracion = ConfiguracionRecord::finder()->findByPk(1);
        $strIdDestinatario = "50000".$arDespacho->IdManifiesto;        
        $strTerceroXML = "<?xml version='1.0' encoding='ISO-8859-1' ?>
                        <root>
                            <acceso>
                                <username>$arConfiguracion->UsuarioWS</username>
                                <password>$arConfiguracion->ClaveWS</password>
                            </acceso>
                            <solicitud>
                                <tipo>1</tipo>
                                <procesoid>11</procesoid>
                            </solicitud>
                            <variables>
                                <NUMNITEMPRESATRANSPORTE>$arConfiguracion->EmpresaWS</NUMNITEMPRESATRANSPORTE>
                                <CODTIPOIDTERCERO>C</CODTIPOIDTERCERO>
                                <NUMIDTERCERO>$strIdDestinatario</NUMIDTERCERO>
                                <NOMIDTERCERO>PROPIETARIO</NOMIDTERCERO>                                   
                                <PRIMERAPELLIDOIDTERCERO>VARIOS</PRIMERAPELLIDOIDTERCERO>
                                <SEGUNDOAPELLIDOIDTERCERO>VARIOS</SEGUNDOAPELLIDOIDTERCERO>                                   
                                <CODSEDETERCERO>1</CODSEDETERCERO>
                                <NOMSEDETERCERO>PRINCIPAL</NOMSEDETERCERO>                                    
                                <NUMTELEFONOCONTACTO>3333333</NUMTELEFONOCONTACTO>
                                <NOMENCLATURADIRECCION>CALLE PRINCIPAL</NOMENCLATURADIRECCION>
                                <CODMUNICIPIORNDC>" . $arDespacho->CiudadOrigen->CodMinTrans . "</CODMUNICIPIORNDC>
                            </variables>
                        </root>";        
        return $strTerceroXML;
    }    
}
?>