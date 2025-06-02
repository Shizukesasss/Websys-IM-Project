function validateForm() {
    const form = document.forms[0];
    const requiredFields = ["name", "birthdate", "email", "mobile", "clinic_branch", "reason", "preferred_date", "preferred_time"];

    for (let field of requiredFields) {
        if (form[field].value.trim() === "") {
            alert(`Please fill out the ${field.replace("_", " ")} field.`);
            return false;
        }
    }

    const consentChecked = form["consent"].checked;
    if (!consentChecked) {
        alert("You must agree to the Privacy Consent.");
        return false;
    }

    return true;
}
