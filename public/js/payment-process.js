document.addEventListener("DOMContentLoaded", () => {
    // Sélectionner le formulaire de paiement dans la page de passer commande
    const checkoutForm = document.querySelector('form[action*="confirmer"]')

    if (checkoutForm) {
        // Intercepter la soumission du formulaire de commande
        checkoutForm.addEventListener("submit", (e) => {
            // Empêcher la soumission normale du formulaire
            e.preventDefault()

            // Récupérer la méthode de paiement sélectionnée
            const selectedPaymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value

            // Vérifier si les informations nécessaires sont remplies
            let isValid = true
            let errorMessage = ""

            if (selectedPaymentMethod === "card") {
                // Valider les champs de carte bancaire
                const cardNumber = document.getElementById("cardNumber")?.value.replace(/\s/g, "") || ""
                const cardExpiry = document.getElementById("cardExpiry")?.value || ""
                const cardCvc = document.getElementById("cardCvc")?.value || ""
                const cardName = document.getElementById("cardName")?.value || ""

                if (cardNumber.length < 16) {
                    isValid = false
                    errorMessage += "Le numéro de carte doit contenir 16 chiffres.\n"
                }

                if (!cardExpiry.match(/^\d{2}\/\d{2}$/)) {
                    isValid = false
                    errorMessage += "La date d'expiration doit être au format MM/AA.\n"
                }

                if (cardCvc.length < 3) {
                    isValid = false
                    errorMessage += "Le code de sécurité doit contenir 3 chiffres.\n"
                }

                if (cardName.trim() === "") {
                    isValid = false
                    errorMessage += "Veuillez entrer le nom sur la carte.\n"
                }
            } else if (selectedPaymentMethod === "cashOnDelivery") {
                // Valider les champs pour le paiement à la livraison
                const gouvernorat = document.getElementById("gouvernorat")?.value || ""

                if (gouvernorat === "") {
                    isValid = false
                    errorMessage += "Veuillez sélectionner un gouvernorat pour la livraison.\n"
                }
            }

            if (!isValid) {
                // Afficher les erreurs
                alert("Veuillez corriger les erreurs suivantes:\n" + errorMessage)
                return
            }

            // Ajouter un champ caché pour indiquer que la commande doit être finalisée directement
            const directFinalizeInput = document.createElement("input")
            directFinalizeInput.type = "hidden"
            directFinalizeInput.name = "directFinalize"
            directFinalizeInput.value = "true"
            checkoutForm.appendChild(directFinalizeInput)

            // Modifier le texte du bouton de soumission
            const submitButton = checkoutForm.querySelector('button[type="submit"]')
            if (submitButton) {
                const originalText = submitButton.textContent
                submitButton.textContent = "Traitement en cours..."
                submitButton.disabled = true
            }

            // Soumettre le formulaire
            checkoutForm.submit()
        })
    }

    // Gestion des méthodes de paiement
    const paymentMethods = document.querySelectorAll(".payment-method")
    const cardPaymentForm = document.getElementById("cardPaymentForm")
    const cashOnDeliveryInfo = document.getElementById("cashOnDeliveryInfo")

    paymentMethods.forEach((method) => {
        method.addEventListener("click", function () {
            // Désélectionner tous les autres
            paymentMethods.forEach((m) => m.classList.remove("active"))

            // Sélectionner celui-ci
            this.classList.add("active")

            // Cocher le radio button
            const radio = this.querySelector('input[type="radio"]')
            radio.checked = true

            // Afficher/masquer les formulaires selon la méthode
            if (radio.value === "card") {
                if (cardPaymentForm) cardPaymentForm.style.display = "block"
                if (cashOnDeliveryInfo) cashOnDeliveryInfo.style.display = "none"

                // Mettre à jour le texte du bouton
                const submitButton = document.querySelector('button[type="submit"]')
                if (submitButton) {
                    submitButton.textContent = "Confirmer et payer"
                }
            } else if (radio.value === "cashOnDelivery") {
                if (cardPaymentForm) cardPaymentForm.style.display = "none"
                if (cashOnDeliveryInfo) cashOnDeliveryInfo.style.display = "block"

                // Mettre à jour le texte du bouton
                const submitButton = document.querySelector('button[type="submit"]')
                if (submitButton) {
                    submitButton.textContent = "Confirmer la commande"
                }
            }
        })
    })
})
