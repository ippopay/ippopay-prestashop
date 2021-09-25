var newSubmitButtonIP;
var baseClass = 'btn btn-primary center-block ';
var conditionsFormIP = document.getElementById('conditions-to-approve');
var submitButtonIp = document.getElementById('payment-confirmation');       
window.onload = function(){
    newSubmitButtonIP = document.createElement('button');
    newSubmitButtonIP.id = 'ippopay-pay-button';
    newSubmitButtonIP.className = baseClass + 'not-shown';
    var style =
    '\
    <style>\
    #ippopay-pay-button.shown{\
      display: block;\
    }\
    #ippopay-pay-button.not-shown{\
      display: none;\
    }\
    #payment-confirmation.ip-show{\
      display:block !important;\
    }\
    #payment-confirmation.ip-hide{\
      display:none !important;\
    }\
    </style>';

  newSubmitButtonIP.innerHTML = 'Pay with Ippopay' + style;
    conditionsFormIP.insertAdjacentElement('afterend', newSubmitButtonIP);
    if (!submitButtonIp) {
    return;
  }
  var ip_button = document.querySelector("#ippopay-pay-button");

// Pay button gets clicked
ip_button.addEventListener('click', function(event) {
    placeOrder();
});

  var parent = document.querySelector('#checkout-payment-step');

  parent.addEventListener(
    'change',
    function(e) {
      var target = e.target;
      var type = target.type;

      // We switch the buttons whenever a radio button (payment method)
      // or a checkbox (conditions) is changed
      if (
        (target.getAttribute('data-module-name') && type === 'radio') ||
        type === 'checkbox'
      ) {
        var selected = this.querySelector('input[data-module-name="IppoPay"]')
          .checked;
        if (selected) {
          ip_button.className = baseClass + 'shown';
          document.querySelector("#payment-confirmation").classList.remove("ip-show");
          document.querySelector("#payment-confirmation").classList.add("ip-hide");
        } else {
          ip_button.className = baseClass + 'not-shown';
          document.querySelector("#payment-confirmation").classList.remove("ip-hide");
          document.querySelector("#payment-confirmation").classList.add("ip-show");
        }

        // This returns the first condition that is not checked
        // and works as a truthy value
        ip_button.disabled = !!document.querySelector(
          'input[name^=conditions_to_approve]:not(:checked)'
        );
      }
    },
    true
  );
}

function placeOrder() {

    var submitButtonIp = document.getElementById('payment-confirmation');
    var baseDir = $("#actionUrl").val();

    $.ajax({
        type: 'POST',
        url: baseDir + 'modules/ippopay/ajax.php',
        headers: { "cache-control": "no-cache" },
        async: true,
        cache: false,
        data: 'action=createOrder',
        dataType: "json",
        success: function(data)
        {
            if(data.ip_order_id && data.ip_public_key) {

                if(data.redirect == 1) {

                    window.location.assign("https://pay.ippopay.com/order/" + data.ip_order_id + '?public_key=' + data.ip_public_key + '&redirect=true')

                } else {

                    var options = {
                        "order_id" : data.ip_order_id,
                        "public_key" : data.ip_public_key
                    }

                    var ipay = new Ippopay(options);

                    ipay.open();
                    ippopayHandler(response, function (e) {

                        if(e.data.status == 'success'){

                            // Find the payment form with the correct action
                            var form = document.querySelector(
                              'form[id=payment-form][action$="ippopay/validation"]'
                            );

                            var action = form.getAttribute('action');

                            form.setAttribute(
                              'action',
                              action + '?ippopay_payment_id=' + e.data.transaction_no
                            );

                            let transaction_no = document.createElement("INPUT");
                            Object.assign(transaction_no, {
                              type: "hidden",
                              name: "transaction_no",
                              value: e.data.transaction_no
                            });

                            let status = document.createElement("INPUT");
                            Object.assign(status, {
                              type: "hidden",
                              name: "status",
                              value: 'success'
                            });

                            form.appendChild(transaction_no);
                            form.appendChild(status);

                            submitButtonIp.getElementsByTagName('button')[0].click();

                        }

                        if(e.data.status == 'failure'){

                            // Find the payment form with the correct action
                            var form = document.querySelector(
                              'form[id=payment-form][action$="ippopay/validation"]'
                            );

                            var action = form.getAttribute('action');

                            form.setAttribute(
                              'action',
                              action + '?ippopay_payment_id=' + e.data.transaction_no
                            );

                            let transaction_no = document.createElement("INPUT");
                            Object.assign(transaction_no, {
                              type: "hidden",
                              name: "transaction_no",
                              value: e.data.transaction_no
                            });

                            let status = document.createElement("INPUT");
                            Object.assign(status, {
                              type: "hidden",
                              name: "status",
                              value: 'failure'
                            });

                            form.appendChild(transaction_no);
                            form.appendChild(status);

                            submitButtonIp.getElementsByTagName('button')[0].click();
                        }

                        if(e.data.status == 'closed'){

                            // Find the payment form with the correct action
                            var form = document.querySelector(
                              'form[id=payment-form][action$="ippopay/validation"]'
                            );

                            var action = form.getAttribute('action');

                            form.setAttribute(
                              'action',
                              action + '?ippopay_payment_id=' + e.data.transaction_no
                            );

                            let transaction_no = document.createElement("INPUT");
                            Object.assign(transaction_no, {
                              type: "hidden",
                              name: "transaction_no",
                              value: e.data.transaction_no
                            });

                            let status = document.createElement("INPUT");
                            Object.assign(status, {
                              type: "hidden",
                              name: "status",
                              value: 'closed'
                            });

                            form.appendChild(transaction_no);
                            form.appendChild(status);

                            submitButtonIp.getElementsByTagName('button')[0].click();
                        }
                    });
                }
                
            } else {
                alert("Payment Err : Key is Missing");
                return false;
            }
            
            return false;
        }
    });
    return false;
}