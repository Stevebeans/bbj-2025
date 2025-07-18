class PaymentModel {
  constructor() {
    this.paymentForm = document.getElementById("payment-options");
    this.summaryElement = document.getElementById("bbj-payment-summary");

    console.log("new JS payment");
    this.events();
  }

  events() {
    const selectItems = this.paymentForm.querySelectorAll(".select-option");

    selectItems.forEach(item => {
      item.addEventListener("click", e => {
        this.chooseItem(e);
      });
    });
  }

  chooseItem(e) {
    this.summaryElement.classList.remove("hidden");
    // Remove any existing 'selected' class
    console.log("click");
    this.paymentForm.querySelectorAll(".select-option").forEach(item => {
      item.classList.remove("selected");
    });

    // Add 'selected' class to the clicked item
    const selectedItem = e.target.closest(".select-option");
    selectedItem.classList.add("selected");

    // Get the plan type and value of the clicked item
    const planType = selectedItem.getAttribute("data-plan-type");
    const planValue = selectedItem.getAttribute("data-value");

    // Update the form fields with the selected plan type
    this.paymentForm.querySelector("#field_plan-type").value = planType;
    this.paymentForm.querySelector("#field_stripe-plan-type").value = planType;

    // Update the payment summary
    this.updateSummary(planType, planValue);
  }

  updateSummary(planType, planValue) {
    this.summaryElement.classList.remove("hidden");
    let planName, tax, total;

    if (planType === "1") {
      planName = "Monthly Ad Free Experience";
      tax = (planValue * 0.0).toFixed(2); // Assuming a 5% tax rate
    } else {
      planName = "Annual Ad Free Experience";
      tax = (planValue * 0.0).toFixed(2); // Assuming a 5% tax rate
    }

    total = (parseFloat(planValue) + parseFloat(tax)).toFixed(2);

    console.log("click");

    this.summaryElement.innerHTML = `
      <p><strong>Plan:</strong> ${planName}</p>
      <p><strong>SubTotal:</strong> $${planValue}</p>
      <p><strong>Total:</strong> $${total}</p>

      <p><Br />Notice: This is a recurring subscription. You can cancel at any time.</p>

    `;
  }
}

export default PaymentModel;
