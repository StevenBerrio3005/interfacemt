<?php
class General{    
    public function CrearConexion () {                
        $cliente = "";
        try {
            //$cliente = new SoapClient("http://72.167.52.87:8080/ws/svr008w.dll/wsdl/IBPMServices");
            $cliente = new SoapClient("http://rndcws.mintransporte.gov.co:8080/ws/svr008w.dll/wsdl/IBPMServices");
            //$cliente = new SoapClient("http://rndcws.mintransporte.gov.co:8080/ws/svr008w.dll/wsdl/IBPMServices");
            //"http://rndcws.mintransporte.gov.co:8080/ws/svr008w.dll"
            return $cliente;
        } catch (Exception $e) {
            return "Error al conectar el servicio: " . $e;            
        }        
    }
    
    public static function InsertarErrorWS ($intTipo, $strModulo, $strReferencia, $strError) {
        $arError = new ErroresWSRecord();
        $arError->fecha = date('Y-m-d H:i:s');;
        $arError->tipo = $intTipo;
        $arError->modulo = $strModulo;
        $arError->referencia = $strReferencia;
        $arError->error = $strError;
        $arError->save();
    }
    
    public static function InsertarAprobacion ($strModulo, $strReferencia, $strId) {
        $arAprobacion = new AprobacionesWSRecord();
        $arAprobacion->fecha = date('Y-m-d H:i:s');;        
        $arAprobacion->modulo = $strModulo;
        $arAprobacion->referencia = $strReferencia;
        $arAprobacion->id_aprobacion = $strId;
        $arAprobacion->save();
    }    
}

?>