<?php
prado::using("Application.pages.herramientas.General");
class CumplirManifiesto {
    
    public function CumplirManifiestoLocal($intOrdDespacho) {        
        $booResultados = TRUE;
        $objGeneral = new General();                                       
        $cliente = $objGeneral->CrearConexion();         
        $arDespacho = new DespachosRecord();
        $arDespacho = DespachosRecord::finder()->FindByPk($intOrdDespacho);
        $arDespachoMT = new DespachosControlMTRecord();
        $arDespachoMT = DespachosControlMTRecord::finder()->findByPk($intOrdDespacho);
        if($arDespachoMT->NumeroCumplidoRemesa == "") {
            if($this->EnviarGuiaWebServices($intOrdDespacho, $cliente) == false){
                $booResultados = false;
            }            
        } else {
            if($arDespachoMT->NumeroCumplidoManifiesto == "") {
                if($booResultados == TRUE) {
                    if($this->EnviarManifiestoWebServices($intOrdDespacho, $cliente) == false){
                        $booResultados = false;
                    }            
                }             
            } else {
                $arDespachoMT = new DespachosControlMTRecord();
                $arDespachoMT = DespachosControlMTRecord::finder()->FindByPk($intOrdDespacho);
                $arDespachoMT->Cumplido = 1;
                $arDespachoMT->save();                        
            }            
        }
       
        return $booResultados;
    }

    public function EnviarGuiaWebServices($intOrdDespacho, $cliente){;
        $boolResultadosEnvio = False;
        $boolErroresDatos = FALSE;
        $arDespacho = new DespachosRecord();
        $arDespacho = DespachosRecord::finder()->FindByPk($intOrdDespacho);
        $strRegistroWS = "";           
        $strXmlGuia = array('' => $this->GenerarXMLGuia($intOrdDespacho));
        while ($boolResultadosEnvio == FALSE && $boolErroresDatos == FALSE) {
            $respuesta = "";
            try {
                $respuesta = $cliente->__soapCall('AtenderMensajeRNDC', $strXmlGuia);
                $cadena_xml = simplexml_load_string($respuesta);
                if($cadena_xml->ErrorMSG != "") {
                    if(substr(strtoupper($cadena_xml->ErrorMSG),0,9) == "DUPLICADO") {
                        $boolResultadosEnvio = TRUE;                        
                    } elseif(substr($cadena_xml->ErrorMSG, 0, 19) == "Error al abrir sesi" || substr($cadena_xml->ErrorMSG, 0, 23) == "Error al realizar conex") {
                        sleep(3);                                
                    }
                    else {
                        General::InsertarErrorWS(2, "Cumplir remesa", $arDespacho->IdManifiesto, utf8_decode($cadena_xml->ErrorMSG));
                        $boolErroresDatos = TRUE;
                    }                            
                }
                if($cadena_xml->ingresoid) {
                    General::InsertarErrorWS(2, "Cumplir remesa", $arDespacho->IdManifiesto, utf8_decode($cadena_xml->ingresoid));
                    General::InsertarAprobacion("Cumplir remesa", $arDespacho->IdManifiesto, utf8_decode($cadena_xml->ingresoid));
                    $strRegistroWS = utf8_decode($cadena_xml->ingresoid);
                    $boolResultadosEnvio = true;
                }
            } catch (Exception $e) {
                if(substr($e, 0, 19 ) == "SoapFault exception") {
                    sleep(3);                            
                } else {
                    General::InsertarErrorWS(1, "General", "", "Error al enviar parametros" . $e);
                    $boolErroresDatos = TRUE;
                }
            }                    
        }

        if($boolResultadosEnvio == true) {
            $this->ActualizarCumplirRemesa($intOrdDespacho, $strRegistroWS);            
        }
        
        return $boolResultadosEnvio;
    }           
    
    public function EnviarManifiestoWebServices($intOrdDespacho, $cliente){;
        $boolResultadosEnvio = False;
        $boolErroresDatos = FALSE;
        $arDespacho = new DespachosRecord();
        $arDespacho = DespachosRecord::finder()->FindByPk($intOrdDespacho);
        $strRegistroWS = "";        
        $strXmlManifiesto = array('' => $this->GenerarXMLManifiesto($intOrdDespacho));
        while ($boolResultadosEnvio == FALSE && $boolErroresDatos == FALSE) {
            $respuesta = "";
            try {
                $respuesta = $cliente->__soapCall('AtenderMensajeRNDC', $strXmlManifiesto);
                $cadena_xml = simplexml_load_string($respuesta);
                if($cadena_xml->ErrorMSG != "") {
                    if(substr(strtoupper($cadena_xml->ErrorMSG),0,9) == "DUPLICADO") {
                        $boolResultadosEnvio = TRUE;
                    } elseif(substr($cadena_xml->ErrorMSG, 0, 19) == "Error al abrir sesi" || substr($cadena_xml->ErrorMSG, 0, 23) == "Error al realizar conex") {
                        sleep(3);                                
                    }
                    else {
                        General::InsertarErrorWS(2, "Cumnplir manifiesto", $arDespacho->IdManifiesto, utf8_decode($cadena_xml->ErrorMSG));
                        $boolErroresDatos = TRUE;
                    }                            
                }
                if($cadena_xml->ingresoid) {
                    General::InsertarErrorWS(2, "Cumnplir manifiesto", $arDespacho->IdManifiesto, utf8_decode($cadena_xml->ingresoid));
                    General::InsertarAprobacion("Cumplir manifiesto", $arDespacho->IdManifiesto, utf8_decode($cadena_xml->ingresoid));
                    $strRegistroWS = utf8_decode($cadena_xml->ingresoid);
                    $boolResultadosEnvio = true;
                }
            } catch (Exception $e) {
                if(substr($e, 0, 19 ) == "SoapFault exception") {
                    sleep(3);                            
                } else {
                    General::InsertarErrorWS(1, "General", "", "Error al enviar parametros" . $e);
                    $boolErroresDatos = TRUE;
                }
            }                    
        }


        if($boolResultadosEnvio == true) {
            $this->ActualizarCumplirmManifiesto($intOrdDespacho, $strRegistroWS);
        }
        
        return $boolResultadosEnvio;
    }    

    public function GenerarXMLGuia($intOrdDespacho) {
        $arConfiguracion = new ConfiguracionRecord();
        $arConfiguracion = ConfiguracionRecord::finder()->findByPk(1);        
        $strExpedirGuiaXML = "";
        $arDespacho = new DespachosRecord();
        $arDespacho = DespachosRecord::finder()->with_CiudadOrigen()->with_CiudadDestino()->FindByPk($intOrdDespacho);
        $strGuia = $arConfiguracion->PrefijoGuiaWs.$arDespacho->IdManifiesto;
        $arVehiculo = new VehiculosRecord();
        $arVehiculo = VehiculosRecord::finder()->with_Tenedor()->FindByPk($arDespacho->IdVehiculo);
        $arTerceroConductor = new TercerosRecord();
        $arTerceroConductor = TercerosRecord::finder()->FindByPk($arDespacho->IdConductor);        
        $dateFechaDescargue = new DateTime($arDespacho->FhExpedicion);
        $dateFechaDescargue->add(new DateInterval('P3D'));
        $strFechaDescargue = $dateFechaDescargue->format('d/m/Y');                
        $strFechaCargue = substr($arDespacho->FhExpedicion, 8, 2) . "/" . substr($arDespacho->FhExpedicion, 5, 2) . "/" . substr($arDespacho->FhExpedicion, 0, 4);
        $dateFechaExpedicion = substr($arDespacho->FhExpedicion, 8, 2) . "/" . substr($arDespacho->FhExpedicion, 5, 2) . "/" . substr($arDespacho->FhExpedicion, 0, 4);
        $dateFechaPagoSaldo = substr($arDespacho->FhPagoSaldo, 8, 2) . "/" . substr($arDespacho->FhPagoSaldo, 5, 2) . "/" . substr($arDespacho->FhPagoSaldo, 0, 4);
        if(count($arDespacho) > 0) {                            
            $strExpedirGuiaXML = "<?xml version='1.0' encoding='ISO-8859-1' ?>
                                            <root>
                                             <acceso>
                                              <username>$arConfiguracion->UsuarioWS</username>
                                              <password>$arConfiguracion->ClaveWS</password>
                                             </acceso>
                                             <solicitud>
                                              <tipo>1</tipo>
                                              <procesoid>5</procesoid>
                                             </solicitud>
                                             <variables>
                                                <NUMNITEMPRESATRANSPORTE>$arConfiguracion->EmpresaWS</NUMNITEMPRESATRANSPORTE>
                                                <CONSECUTIVOREMESA>$strGuia</CONSECUTIVOREMESA>
                                                <NUMMANIFIESTOCARGA>$arDespacho->IdManifiesto</NUMMANIFIESTOCARGA>
                                                <TIPOCUMPLIDOREMESA>C</TIPOCUMPLIDOREMESA>
                                                <CANTIDADENTREGADA>$arDespacho->KilosReales</CANTIDADENTREGADA>   
                                                <CANTIDADCARGADA>$arDespacho->KilosReales</CANTIDADCARGADA>
                                                <FECHALLEGADACARGUE>$strFechaCargue</FECHALLEGADACARGUE>
                                                <HORALLEGADACARGUEREMESA>11:20</HORALLEGADACARGUEREMESA>
                                                <FECHAENTRADACARGUE>$strFechaCargue</FECHAENTRADACARGUE>
                                                <HORAENTRADACARGUEREMESA>11:22</HORAENTRADACARGUEREMESA>
                                                <FECHASALIDACARGUE>$strFechaCargue</FECHASALIDACARGUE>
                                                <HORASALIDACARGUEREMESA>11:40</HORASALIDACARGUEREMESA>                                                
                                                <FECHALLEGADADESCARGUE>$strFechaDescargue</FECHALLEGADADESCARGUE>
                                                <HORALLEGADADESCARGUECUMPLIDO>09:33</HORALLEGADADESCARGUECUMPLIDO>
                                                <FECHAENTRADADESCARGUE>$strFechaDescargue</FECHAENTRADADESCARGUE>
                                                <HORAENTRADADESCARGUECUMPLIDO>09:45</HORAENTRADADESCARGUECUMPLIDO>
                                                <FECHASALIDADESCARGUE>$strFechaDescargue</FECHASALIDADESCARGUE>
                                                <HORASALIDADESCARGUECUMPLIDO>12:34</HORASALIDADESCARGUECUMPLIDO>                                                                                                
                                    </variables>
                    </root>";
            }

        return $strExpedirGuiaXML;
    }    
    
    public function GenerarXMLManifiesto($intOrdDespacho) {
        $arConfiguracion = new ConfiguracionRecord();
        $arConfiguracion = ConfiguracionRecord::finder()->findByPk(1);        
        $strExpedirManifiestoXML = "";
        $arDespacho = new DespachosRecord();
        $arDespacho = DespachosRecord::finder()->with_CiudadOrigen()->with_CiudadDestino()->FindByPk($intOrdDespacho);
        $strGuia = $arConfiguracion->PrefijoGuiaWs.$arDespacho->IdManifiesto;
        $arVehiculo = new VehiculosRecord();
        $arVehiculo = VehiculosRecord::finder()->with_Tenedor()->FindByPk($arDespacho->IdVehiculo);
        $arTerceroConductor = new TercerosRecord();
        $arTerceroConductor = TercerosRecord::finder()->FindByPk($arDespacho->IdConductor);        
        $dateFechaCumplidos = new DateTime($arDespacho->FhExpedicion);
        $dateFechaCumplidos->add(new DateInterval('P5D'));
        $strFechaCumplidos = $dateFechaCumplidos->format('d/m/Y');   
        
        $strFechaCargue = substr($arDespacho->FhExpedicion, 8, 2) . "/" . substr($arDespacho->FhExpedicion, 5, 2) . "/" . substr($arDespacho->FhExpedicion, 0, 4);
        $dateFechaExpedicion = substr($arDespacho->FhExpedicion, 8, 2) . "/" . substr($arDespacho->FhExpedicion, 5, 2) . "/" . substr($arDespacho->FhExpedicion, 0, 4);
        $dateFechaPagoSaldo = substr($arDespacho->FhPagoSaldo, 8, 2) . "/" . substr($arDespacho->FhPagoSaldo, 5, 2) . "/" . substr($arDespacho->FhPagoSaldo, 0, 4);
        if(count($arDespacho) > 0) {                            
            $strExpedirManifiestoXML = "<?xml version='1.0' encoding='ISO-8859-1' ?>
                                            <root>
                                             <acceso>
                                              <username>$arConfiguracion->UsuarioWS</username>
                                              <password>$arConfiguracion->ClaveWS</password>
                                             </acceso>
                                             <solicitud>
                                              <tipo>1</tipo>
                                              <procesoid>6</procesoid>
                                             </solicitud>
                                             <variables>                                                                                                                                              
                                                <NUMNITEMPRESATRANSPORTE>$arConfiguracion->EmpresaWS</NUMNITEMPRESATRANSPORTE>                                                
                                                <NUMMANIFIESTOCARGA>$arDespacho->IdManifiesto</NUMMANIFIESTOCARGA>
                                                <TIPOCUMPLIDOMANIFIESTO>C</TIPOCUMPLIDOMANIFIESTO>
                                                <FECHAENTREGADOCUMENTOS>$strFechaCumplidos</FECHAENTREGADOCUMENTOS>
                                                <VALORADICIONALHORASCARGUE>0</VALORADICIONALHORASCARGUE>
                                                <VALORDESCUENTOFLETE>0</VALORDESCUENTOFLETE>                                                
                                                <VALORSOBREANTICIPO>0</VALORSOBREANTICIPO>                                                                                                                                               
                                    </variables>
                    </root>";
            }

        return $strExpedirManifiestoXML;
    }    

    public function ActualizarCumplirRemesa($intOrdDespacho, $strRegistroWS) {    
        if($strRegistroWS != "") {
            $arDespachoMT = new DespachosControlMTRecord();
            $arDespachoMT = DespachosControlMTRecord::finder()->FindByPk($intOrdDespacho);
            if(count($arDespachoMT) > 0) {
                $arDespachoMT->NumeroCumplidoRemesa = $strRegistroWS;
                $arDespachoMT->save();                 
            }
           
        }
    }
    
    public function ActualizarCumplirmManifiesto($intOrdDespacho, $strRegistroWS) {    
        if($strRegistroWS != "") {
            $arDespachoMT = new DespachosControlMTRecord();
            $arDespachoMT = DespachosControlMTRecord::finder()->FindByPk($intOrdDespacho);            
            $arDespachoMT->NumeroCumplidoManifiesto = $strRegistroWS;
            $arDespachoMT->Cumplido = 1;
            $arDespachoMT->save();            
        }
    }    
}
?>