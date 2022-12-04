<?php
prado::using("Application.pages.herramientas.EnviarTerceros");
prado::using("Application.pages.herramientas.EnviarVehiculo");
prado::using("Application.pages.herramientas.EnviarRemesas");
prado::using("Application.pages.herramientas.EnviarManifiesto");

class Despachos extends TPage {
    public function OnInit($param) {
        parent::OnInit($param);
        if (!$this->IsPostBack) {
            $this->cargarDespachos();
            $this->cargarErrores();
        }
    }    
    
    public function procesarDespachoUnico($sender, $param) {
        $registro = $param->Item;   
        $intOrdDespacho = $this->DGDespachos->Datakeys[$registro->ItemIndex];                
        $this->EnviarDespacho($intOrdDespacho);
    }
    
    public function reiniciarEnvioDespacho($sender, $param) {
        $registro = $param->Item;   
        $intOrdDespacho = $this->DGDespachos->Datakeys[$registro->ItemIndex];                
        $arDespachosControlMt = new DespachosControlMTRecord();
        $arDespachosControlMt = DespachosControlMTRecord::finder()->findByPk($intOrdDespacho);
        $arDespachosControlMt->EnvioPersona = 0;
        $arDespachosControlMt->EnvioVehiculo = 0;
        $arDespachosControlMt->EnvioGuias = 0;
        $arDespachosControlMt->save();
        $this->cargarErrores();
        $this->cargarDespachos();
    }    

    public function NoReportar($sender, $param) {
        $registro = $param->Item;   
        $intOrdDespacho = $this->DGDespachos->Datakeys[$registro->ItemIndex];                
        $arDespachosControlMt = new DespachosControlMTRecord();
        $arDespachosControlMt = DespachosControlMTRecord::finder()->findByPk($intOrdDespacho);
        $arDespachosControlMt->EnvioManifiesto = 1;
        $arDespachosControlMt->NoReportado = 1;
        $arDespachosControlMt->save();
        
        $this->cargarErrores();
        $this->cargarDespachos();
    }        
    
    public function procesarDespacho($intOrdDespacho) {        
        $intResultados = 3;
        $intNumeroIntentos = 30;
        $objGeneral = new General();                                       
        $cliente = $objGeneral->CrearConexion();
        $arDespacho = new DespachosRecord();
        $arDespacho = DespachosRecord::finder()->FindByPk($intOrdDespacho);        
        $arDespachoControMT = new DespachosControlMTRecord();
        $arDespachoControMT = DespachosControlMTRecord::finder()->findByPk($intOrdDespacho);
        $arVehiculo = new VehiculosRecord();
        $arVehiculo = VehiculosRecord::finder()->findByPk($arDespacho->IdVehiculo);
        
        if(!is_object($cliente))
            $this->LblMensaje->text = $cliente;
        else {
            //Procesar personas          
            if($arDespachoControMT->EnvioPersona == 0) {
                $intIntentos = 0;
                while ($intResultados != 1 && $intIntentos < 30) {
                    $intResultados = $this->enviarPersonasDespacho($cliente, $intOrdDespacho);    
                    $intIntentos = $intIntentos + 1;
                }                               
                if($intResultados == 1)
                    $arDespachoControMT->EnvioPersona = 1;
            }
            else
                $intResultados = 1;
            
            if($intResultados == 1) {
                //Procesar vehiculo          
                if($arDespachoControMT->EnvioVehiculo == 0) {
                    $intResultados = $this->enviarVehiculoDespacho($cliente, $intOrdDespacho);
                    if($intResultados == 1)
                        $arDespachoControMT->EnvioVehiculo = 1;
                }                
            }                        

            if($intResultados == 1) {         
                if($arDespachoControMT->ExpedirRemesas == 0) {
                    $intResultados = $this->expedirRemesasDespacho($cliente, $intOrdDespacho);
                    if($intResultados == 1)
                        $arDespachoControMT->ExpedirRemesas = 1;
                }                
            }            

            if($intResultados == 1) {         
                if($arDespachoControMT->ExpedirManifiesto == 0) {
                    $intResultados = $this->expedirManifiestoDespacho($cliente, $intOrdDespacho);
                    if($intResultados == 1)
                        $arDespachoControMT->ExpedirManifiesto = 1;
                }                
            }            
            
            $arDespachoControMT->save();                      
            
        }
        $this->cargarErrores();
        $this->cargarDespachos();
    }        
            
    public function cargarErrores() {
        $arErrores = new ErroresWSRecord();
        $criteria = new TActiveRecordCriteria;            
        $criteria->OrdersBy['codigo'] = 'desc';
        $criteria->Limit = 20;
        $arErrores = ErroresWSRecord::finder()->FindAll($criteria);
        $this->DGErrores->DataSource = $arErrores;
        $this->DGErrores->DataBind();        
    }
    
    public function cargarDespachos() {
        $strSql = "SELECT despachos_control_mt.*, despachos.FhExpedicion "
                . "FROM despachos_control_mt "
                . "LEFT JOIN despachos ON despachos_control_mt.OrdDespacho = despachos.OrdDespacho "                
                . "WHERE EnvioManifiesto = 0 "
                . "ORDER BY OrdDespacho";
        $arDespachos = new DespachosControlMTRecord();
        $arDespachos = DespachosControlMTRecord::finder('DespachosControlMTExtRecord')->FindAllBySql($strSql);
        $this->DGDespachos->DataSource = $arDespachos;
        $this->DGDespachos->DataBind();        
    }    
    
    public function EnviarDespacho($intOrdDespacho) {
        set_time_limit(0);
        $objEnviarTerceros = new EnviarTerceros();
        $objEnviarVehiculo = new EnviarVehiculo();
        $objEnviarRemesas = new EnviarRemesas();
        $objEnviarManifiesto = new EnviarManifiesto();
        
        $arDespacho = new DespachosRecord();
        $arDespacho = DespachosRecord::finder()->FindByPk($intOrdDespacho);        
        $arDespachoControMT = new DespachosControlMTRecord();
        $arDespachoControMT = DespachosControlMTRecord::finder()->findByPk($intOrdDespacho);
        if($arDespachoControMT->EnvioPersona == 1) {
            if($arDespachoControMT->EnvioVehiculo == 1) {
                if($arDespachoControMT->EnvioGuias == 1) {
                    if($objEnviarManifiesto->EnviarManifiestoLocal($intOrdDespacho) == TRUE) {
                        $arDespachoControMT = DespachosControlMTRecord::finder()->findByPk($intOrdDespacho);
                        $arDespachoControMT->EnvioManifiesto = 1;
                        $arDespachoControMT->save();
                    }
                }
                else {
                    if($objEnviarRemesas->EnviarRemesasManifiesto($intOrdDespacho) == TRUE) {
                        $arDespachoControMT = DespachosControlMTRecord::finder()->findByPk($intOrdDespacho);
                        $arDespachoControMT->EnvioGuias = 1;
                        $arDespachoControMT->save();
                    }                    
                }
            }
            else {
                if($objEnviarVehiculo->EnviarVehiculoManifiesto($intOrdDespacho) == TRUE) {                    
                    $arDespachoControMT = DespachosControlMTRecord::finder()->findByPk($intOrdDespacho);
                    $arDespachoControMT->EnvioVehiculo = 1;
                    $arDespachoControMT->save();
                }                   
            }                           
        }
        else {
            if($objEnviarTerceros->EnviarTercerosManifiesto($intOrdDespacho) == TRUE) {
                $arDespachoControMT = DespachosControlMTRecord::finder()->findByPk($intOrdDespacho);
                $arDespachoControMT->EnvioPersona = 1;
                $arDespachoControMT->save();                                                                     
            }           
        }        
        $this->cargarErrores();
        $this->cargarDespachos();
    }
    
    public function changePage($sender, $param) {
        $this->DGDespachos->CurrentPageIndex = $param->NewPageIndex; // Recupera la p�gina que ha sido seleccionada y que ser� mostrada.
        $this->cargarDespachos();
    }

    public function pagerCreated($sender, $param) {
        $param->Pager->Controls->insertAt(0, 'page: ');
    }    
}

?>