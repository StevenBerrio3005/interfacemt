<?php
prado::using("Application.pages.herramientas.General");
class EnviarRemesas {
    public function EnviarRemesasManifiesto($intOrdDespacho) {
        $booResultados = TRUE;
        $objGeneral = new General();                                       
        $cliente = $objGeneral->CrearConexion();        
        $arDespacho = new DespachosRecord();
        $arDespacho = DespachosRecord::finder()->FindByPk($intOrdDespacho);                
        if($this->EnviarGuiaWebServices($arDespacho, $cliente) == false){
            $booResultados = false;
        }          
        return $booResultados;
    }
    
    public function EnviarGuiaWebServices($arDespacho, $cliente){
        $boolResultadosEnvio = False;  
        $boolErroresDatos = FALSE;
        if($this->ValidarDatosGuia() == true) {
            $strXmlGuia = array('' => $this->GenerarXMLGuia($arDespacho));
            while ($boolResultadosEnvio == FALSE && $boolErroresDatos == FALSE) {
                $respuesta = "";
                try {
                    $respuesta = $cliente->__soapCall('AtenderMensajeRNDC', $strXmlGuia);
                    $cadena_xml = simplexml_load_string($respuesta);
                    if($cadena_xml->ErrorMSG != "") {
                        if(substr(strtoupper($cadena_xml->ErrorMSG),0,9) == "DUPLICADO") {
                            $boolResultadosEnvio = true;                          
                        } elseif(substr($cadena_xml->ErrorMSG, 0, 19) == "Error al abrir sesi" || substr($cadena_xml->ErrorMSG, 0, 23) == "Error al realizar conex") {
                            sleep(3);                                
                        }                        
                        else {
                            General::InsertarErrorWS(2, "Remesa", $arDespacho->IdManifiesto, utf8_decode($cadena_xml->ErrorMSG));                            
                            $boolErroresDatos = TRUE;
                        }
                    }
                    if($cadena_xml->ingresoid) {
                        General::InsertarErrorWS(2, "Remesa", $arDespacho->IdManifiesto, utf8_decode($cadena_xml->ingresoid));                        
                        General::InsertarAprobacion("Remesa", $arDespacho->IdManifiesto, utf8_decode($cadena_xml->ingresoid));
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
        }
        else
            $boolResultadosEnvio = false; 

        if($boolResultadosEnvio == true) {
            $this->ActualizarGuia($arDespacho);
        }            
                    
        return $boolResultadosEnvio;
    }
    
    public function ValidarDatosGuia () {
        $intResultadoValidacion = TRUE;
        return $intResultadoValidacion;            
    }
    
    public function GenerarXMLGuia($arDespacho) {
        $arConfiguracion = new ConfiguracionRecord();
        $arConfiguracion = ConfiguracionRecord::finder()->findByPk(1); 
        $strGuia = $arConfiguracion->PrefijoGuiaWs.$arDespacho->IdManifiesto;
        $arInformacionEmpresa = new InformacionEmpresaRecord();
        $arInformacionEmpresa = InformacionEmpresaRecord::finder()->findByPk(1);
        $strExpedirRemesaXML = "";
        $strIdDestinatario = "10000".$arDespacho->IdManifiesto;
        $strIdPropietario = "50000".$arDespacho->IdManifiesto;
        $dateFechaVencePoliza = substr($arInformacionEmpresa->VencePoliza, 8, 2) . "/" . substr($arInformacionEmpresa->VencePoliza, 5, 2) . "/" . substr($arInformacionEmpresa->VencePoliza, 0, 4);
        $dateFechaCargue = substr($arDespacho->FhExpedicion, 8, 2) . "/" . substr($arDespacho->FhExpedicion, 5, 2) . "/" . substr($arDespacho->FhExpedicion, 0, 4);        
        $dateFechaPactadaCargue = substr($arDespacho->FhExpedicion, 8, 2) . "/" . substr($arDespacho->FhExpedicion, 5, 2) . "/" . substr($arDespacho->FhExpedicion, 0, 4);
        $dateFechaPactadaDescargueCargue = substr($arDespacho->FhPagoSaldo, 8, 2) . "/" . substr($arDespacho->FhPagoSaldo, 5, 2) . "/" . substr($arDespacho->FhPagoSaldo, 0, 4);
        $strExpedirRemesaXML ="<?xml version='1.0' encoding='ISO-8859-1' ?>
                                <root>
                                    <acceso>
                                        <username>$arConfiguracion->UsuarioWS</username>
                                        <password>$arConfiguracion->ClaveWS</password>
                                    </acceso>
                                    <solicitud>
                                        <tipo>1</tipo>
                                        <procesoid>3</procesoid>
                                    </solicitud>
                                    <variables>
                                        <NUMNITEMPRESATRANSPORTE>$arConfiguracion->EmpresaWS</NUMNITEMPRESATRANSPORTE>
                                        <CONSECUTIVOREMESA>$strGuia</CONSECUTIVOREMESA>
                                        <CODOPERACIONTRANSPORTE>P</CODOPERACIONTRANSPORTE>
                                        <CODTIPOEMPAQUE>0</CODTIPOEMPAQUE>
                                        <CODNATURALEZACARGA>1</CODNATURALEZACARGA>                                                  
                                        <DESCRIPCIONCORTAPRODUCTO>PAQUETES VARIOS</DESCRIPCIONCORTAPRODUCTO>
                                        <MERCANCIAREMESA>009880</MERCANCIAREMESA>
                                        <CANTIDADCARGADA>$arDespacho->KilosReales</CANTIDADCARGADA>
                                        <UNIDADMEDIDACAPACIDAD>1</UNIDADMEDIDACAPACIDAD>
                                        <CODTIPOIDREMITENTE>C</CODTIPOIDREMITENTE>
                                        <NUMIDREMITENTE>$strIdPropietario</NUMIDREMITENTE>
                                        <CODSEDEREMITENTE>1</CODSEDEREMITENTE>
                                        <CODTIPOIDDESTINATARIO>C</CODTIPOIDDESTINATARIO>
                                        <NUMIDDESTINATARIO>$strIdDestinatario</NUMIDDESTINATARIO>
                                        <CODSEDEDESTINATARIO>1</CODSEDEDESTINATARIO>
                                        <CODTIPOIDPROPIETARIO>C</CODTIPOIDPROPIETARIO>
                                        <NUMIDPROPIETARIO>$strIdPropietario</NUMIDPROPIETARIO>
                                        <CODSEDEPROPIETARIO>1</CODSEDEPROPIETARIO>
                                        <DUENOPOLIZA>E</DUENOPOLIZA>
                                        <NUMPOLIZATRANSPORTE>$arInformacionEmpresa->NroPoliza</NUMPOLIZATRANSPORTE>
                                        <FECHAVENCIMIENTOPOLIZACARGA>$dateFechaVencePoliza</FECHAVENCIMIENTOPOLIZACARGA>
                                        <COMPANIASEGURO>$arInformacionEmpresa->NitAseguradora</COMPANIASEGURO>
                                        <HORASPACTOCARGA>24</HORASPACTOCARGA>
                                        <MINUTOSPACTOCARGA>00</MINUTOSPACTOCARGA>
                                        <FECHACITAPACTADACARGUE>21/08/2013</FECHACITAPACTADACARGUE>
                                        <HORACITAPACTADACARGUE>22:00</HORACITAPACTADACARGUE>
                                        <HORASPACTODESCARGUE>72</HORASPACTODESCARGUE>
                                        <MINUTOSPACTODESCARGUE>00</MINUTOSPACTODESCARGUE>
                                        <FECHACITAPACTADADESCARGUE>25/08/2013</FECHACITAPACTADADESCARGUE>
                                        <HORACITAPACTADADESCARGUEREMESA>08:00</HORACITAPACTADADESCARGUEREMESA>
                                    </variables>
		  		</root>";                       
        return $strExpedirRemesaXML;
    }  
    
    public function ActualizarGuia($arDespachoParametro) {
        $arDespacho = new DespachosRecord();
        $arDespacho = DespachosRecord::finder()->FindByPk($arDespachoParametro->OrdDespacho);
        $arDespacho->EnviadoGuia = 1;
        $arDespacho->save();
    }
}
?>