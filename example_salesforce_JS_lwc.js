import { LightningElement, api } from 'lwc';
import {ShowToastEvent} from 'lightning/platformShowToastEvent';

const actions = [
    { label: 'Delete', name: 'deleteRow' }
];

export default class OpportunityRatingPaymentPlan extends LightningElement {
    @api product;
    @api currentuserpermission;
    @api expirationDate = '';
    @api expirationOdometer = '';

    errors = [];
    dataErrors = [];
    showErrors = false;

    finalprice = 0;

    paymentPlanData = [];
    draftValues = [];
    paymentPlanColumns = [
        { label: '# Payments', fieldName: 'payments', editable: true, hideDefaultActions: true },
        { label: 'Down Percentage', fieldName: 'percentage', type: 'longPercent', editable: true, hideDefaultActions: true },
        { label: 'Down Amount', fieldName: 'amount', type: 'currency', editable: true, hideDefaultActions: true },
        { label: 'Monthly Payment', fieldName: 'monthly', type: 'currency', editable: true,  typeAttributes: {maximumFractionDigits: 2}, hideDefaultActions: true },
        { label: 'Profit', fieldName: 'profit', type: 'currency', typeAttributes: {maximumFractionDigits: 2}, hideDefaultActions: true },
        { label: ' ', fieldName: 'id', type: 'deleteRowButton', hideDefaultActions: true, cellAttributes: {alignment: 'center'} },
        { type: 'action', typeAttributes: { rowActions: actions } }
    ];

    connectedCallback() {
        // {ADDMONTHS(CurrentDate,{Product Term}}
        let expDate = new Date();       
        expDate.setMonth(expDate.getMonth() + this.product.month);
        let year = expDate.toLocaleString("default", { year: "numeric" });
        let month = expDate.toLocaleString("default", { month: "2-digit" });
        let day = expDate.toLocaleString("default", { day: "2-digit" });
        this.expirationDate = year + '-' + month + '-' + day;
        
        //{IF(Add_Max = 'Max', Mileage, IF(Add_Max = 'Add', Current Odometer + Mileage, null))
        this.expirationOdometer = this.product.addmax == 'Max' ? this.product.miles : this.product.odometr + this.product.miles;
        this.paymentPlanData = [...this.product.paymentPlans];

        this.finalprice = this.product.retailcost + this.product.tax - this.product.discount;
        this.finalprice = Math.round(this.finalprice * 100) / 100;
    }

    /****************** EVENT HANDLERS ******************/
   
    handleAddPaymentPlan(event) {
        let paymentPlan = [...this.paymentPlanData];
        let maxPayments = ((this.product.month/2)-1);

        let downAmountPercentage = 10;
        let downAmount = this.finalprice*0.1;
        let monthlyAmount = (this.finalprice - this.finalprice*0.1) / maxPayments;
        
        paymentPlan.push({id: this.paymentPlanData.length, payments: maxPayments, percentage: downAmountPercentage, amount: downAmount, monthly: monthlyAmount, profit: 0});
        this.paymentPlanData = paymentPlan;

        this.updateProduct(); 
    }

    handleSave(event) {
        this.errors = [];
        this.dataErrors = [];
        this.showErrors = false;

        let draftRows = event.target.draftValues;       
        let paymentPlan = [this.paymentPlanData[0]];

        this.paymentPlanData.forEach(row =>{
            let option = { ...row };

            draftRows.forEach(draftRow => {
                let tmpPayments = draftRow.hasOwnProperty('payments') ? draftRow.payments : option.payments;
                let tmpMonthly =  draftRow.hasOwnProperty('monthly') ? draftRow.monthly : option.monthly;
                let tmpPercentage = draftRow.hasOwnProperty('percentage') ? draftRow.percentage : option.percentage;
                let tmpAmount =  draftRow.hasOwnProperty('amount') ? draftRow.amount : option.amount;

                // CHECK IF CHANGED
                let chandedAmount = false;
                let changedMonthly = false;
                let changedPayments = false;
                let changedPercentage = false;
                if(draftRow.hasOwnProperty('amount') && option.amount != draftRow.amount) chandedAmount = true;
                if(draftRow.hasOwnProperty('monthly') && option.monthly != draftRow.monthly) changedMonthly = true;
                if(draftRow.hasOwnProperty('payments') && option.payments != draftRow.payments) changedPayments = true;
                if(draftRow.hasOwnProperty('percentage') && option.percentage != draftRow.percentage) changedPercentage = true; ;                

                if(option.id != 0 && draftRow.id == option.id) {

                    this.paymentPlanValidation(draftRow);

                    if(this.errors.length == 0){

                        // VARIANTS OF CALCULATION
                        if(!chandedAmount && !changedMonthly && !changedPayments && changedPercentage){
                            option.percentage = tmpPercentage;
                            option.payments = tmpPayments;
                            option.amount = this.finalprice * tmpPercentage / 100;
                            option.monthly = (this.finalprice - option.amount) / tmpPayments;
                        } else if(!chandedAmount && !changedMonthly && changedPayments && !changedPercentage){
                            option.amount = tmpAmount;
                            option.percentage = tmpPercentage;
                            option.payments = tmpPayments;
                            option.monthly = (this.finalprice - tmpAmount) / tmpPayments;

                        } else if(chandedAmount && !changedMonthly && !changedPayments && !changedPercentage){
                            option.amount = tmpAmount;
                            option.percentage = tmpAmount / this.finalprice * 100;
                            option.payments = tmpPayments;
                            option.monthly = (this.finalprice - tmpAmount) / tmpPayments;
                        } else if(!chandedAmount && changedMonthly && !changedPayments && !changedPercentage){
                            option.monthly = tmpMonthly;  
                            option.payments = tmpPayments;
                            tmpAmount = this.finalprice - tmpMonthly * tmpPayments
                            option.amount = tmpAmount;
                            option.percentage = tmpAmount / this.finalprice * 100;                        
                        } else {
                            // OTHERS VARIANT
                            // LET option.amount & option.payments BE THE MAIN
                            option.amount = tmpAmount;
                            option.payments = tmpPayments;
                            option.percentage = tmpAmount / this.finalprice * 100;
                            option.monthly = (this.finalprice - option.amount) / tmpPayments;
                        }

                        this.paymentPlanValidation(option);

                        // TODO
                        option.profit = 0;
                    }
                }

                if((option.id == 0 && draftRow.id == 'row-' + option.id) && (chandedAmount || changedMonthly || changedPayments || changedPercentage)){
                    this.errors.push({row: option.id, key: this.errors.length, message: 'You cant modify or delete the first row of the table.', field: 'amount'}); 
                }
            });

            if(option.id != 0) paymentPlan.push(option);   
        });

        if(this.errors.length == 0){
            this.paymentPlanData = paymentPlan;
            this.draftValues = [];        
            this.updateProduct();            
        } else {
            let tmpErrors = {};
            tmpErrors.rows = {};
                       
            this.errors.forEach(error => {
                if(error.row == 0){
                    if(!('table' in tmpErrors)) tmpErrors.table = {
                        title: 'Your entry cannot be saved. Fix the errors and try again.',
                        messages: []
                    }; 
                    tmpErrors.table.messages.push(error.message);
                }
                if(!(error.row in tmpErrors.rows)) tmpErrors.rows[error.row] = {title: 'We found an error', messages: [], fields: []}
                tmpErrors.rows[error.row].messages.push(error.message);
                tmpErrors.rows[error.row].fields.push(error.field);
            })

            this.dataErrors = tmpErrors;
            this.errors = [];
        }
    }

    handleRowAction(event){        
        const actionName = event.detail.action.name;
        const row = event.detail.row;
        if(event.detail.row.id != 0 && actionName == 'deleteRow'){
            this.deleteRow(row);            
        } else {
            this.errors.push({key: this.errors.length, message: 'You cant modify or delete the first row of the table.'});
            this.showErrors = true;
            setTimeout(() => {
                this.errors = [];
                this.showErrors = false;
            }, 5000);
        }
    }

    handleDeleteRow(event){
        console.log('DELETE');
    }

    /****************** UTILITIES ******************/

    updateProduct() {
        this.dispatchEvent(new CustomEvent('updateproduct', {
            detail: {
                productId: this.product.id, 
                paymentPlans: this.paymentPlanData
            }
        }));
    }
    
    deleteRow(row) {
        const { id } = row;
        const index = this.findRowIndexById(id);
        if (index !== -1) {
            this.paymentPlanData = this.paymentPlanData
                .slice(0, index)
                .concat(this.paymentPlanData.slice(index + 1));
        }
    }

    findRowIndexById(id) {
        let ret = -1;
        this.paymentPlanData.some((row, index) => {
            if (row.id === id) {
                ret = index;
                return true;
            }
            return false;
        });
        return ret;
    }

    paymentPlanValidation(plan){
        // PAYMENTS
        if(plan.payments < 0){
            this.errors.push({row: plan.id, key: this.errors.length, message: '"No. of Payments" can\'t be negative.', field: 'payments'});
        } 

        if(plan.payments == 0){
            this.errors.push({row: plan.id, key: this.errors.length, message: '"No. of Payments" can\'t be empty.', field: 'payments'});
        } 

        if(plan.payments >= this.product.month/2){
            if(!this.currentuserpermission.canThirtySixMonths) 
                this.errors.push({row: plan.id, key: this.errors.length, message: '"No. of Payments" can\'t be more of half full contract months.', field: 'payments'});
        } 

        if(plan.payments> 24 && (this.product.month/2 - 1) > 24){
            if(!this.currentuserpermission.canThirtySixMonths) 
                this.errors.push({row: plan.id, key: this.errors.length, message: 'The greatest value of "No. of Payments" must be 24 months.', field: 'payments'});
        } 

        if(this.currentuserpermission.canThirtySixMonths && plan.payments > 35){
            let maxValue = 0;
            if(this.product.month < 36 && plan.payments > 35) maxValue = 35;
            if(this.product.month == 36 && plan.payments > 33) maxValue = 33;
            if(this.product.month > 37 && plan.payments > 36) maxValue = 36;
            if(maxValue > 0) this.errors.push({row: plan.id, key: this.errors.length, message: 'The greatest value of "No. of payments" must be ' + maxValue + ' or less', field: 'payments'});
        }

        if(this.currentuserpermission.canEnterMaxNumberOfPayments && plan.payments > this.product.month){
            this.errors.push({row: plan.id, key: this.errors.length, message: 'The greatest value of "No. of payments" must be ' + this.product.month + ' or less', field: 'payments'});
        } 

        // PERCENTAGE  
        if(!this.currentuserpermission.canEnterLessThanTenPercentDown){
            let minPercentage = 0
            if(this.product.month <= 12 && plan.percentage < 10) minPercentage = 10;
            if(this.product.month >= 12 && this.product.month <= 36 && plan.percentage < 5) minPercentage = 5;
            if(this.product.month > 37 && plan.percentage < 3) minPercentage = 3;
            if(minPercentage > 0) this.errors.push({row: plan.id, key: this.errors.length, message: 'The minimal value of payment percentage must be ' + minPercentage + ' or more.', field: 'percentage'});
        }

        if(plan.percentage > 100) {
            this.errors.push({row: plan.id, key: this.errors.length, message: 'The greates value of payment percentage must be 100%.', field: 'percentage'});
        }

        // AMOUNT
        if(plan.amount < 0) this.errors.push({row: plan.id, key: this.errors.length, message: 'Amount can\'t be negative.', field: 'amount'});            
        // MONTHLY
        if(plan.monthly < 0) this.errors.push({row: plan.id, key: this.errors.length, message: 'Monthly amount can\'t be negative.', field: 'monthly'});
    }
}