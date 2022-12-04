<?php
prado::using("Application.pages.herramientas.CumplirManifiesto");

class CumplirDespachos extends TPage {
    public function OnInit($param) {
        parent::OnInit($param);
        if (!$this->IsPostBack) {
            $this->cargarDespachos();
            $this->cargarErrores();
        }
    }                    

    public function Cumplir($sender, $param) {        
        $objCumplirManifiesto = new CumplirManifiesto();
        $registro = $param->Item;
        $intOrdDespacho = $this->DGDespachos->Datakeys[$registro->ItemIndex];                
        if($objCumplirManifiesto->CumplirManifiestoLocal($intOrdDespacho) == TRUE) {
            
        }
        
        $this->cargarErrores();
        $this->cargarDespachos();
    }                

    public function NoCumplir($sender, $param) {        
        $objCumplirManifiesto = new CumplirManifiesto();
        $registro = $param->Item;
        $intOrdDespacho = $this->DGDespachos->Datakeys[$registro->ItemIndex];                
        $arDespachoMT = new DespachosControlMTRecord();
        $arDespachoMT = DespachosControlMTRecord::finder()->FindByPk($intOrdDespacho);
        $arDespachoMT->NoCumplido = 1;
        $arDespachoMT->save();
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
                . "WHERE EnvioManifiesto = 1 AND Cumplido = 0 AND NoCumplido = 0"
                . "ORDER BY OrdDespacho";
        $arDespachos = new DespachosControlMTRecord();
        $arDespachos = DespachosControlMTRecord::finder('DespachosControlMTExtRecord')->FindAllBySql($strSql);
        $this->DGDespachos->DataSource = $arDespachos;
        $this->DGDespachos->DataBind();        
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