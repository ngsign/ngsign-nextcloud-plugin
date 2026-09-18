/* global OC */
const form = document.getElementById('ngsign-settings-form')

if (form) {
	form.addEventListener('submit', async (event) => {
		event.preventDefault()
		const message = document.getElementById('ngsign-settings-message')
		const submit = form.querySelector('[type="submit"]')
		submit.disabled = true
		try {
			const response = await fetch(form.action, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8', requesttoken: OC.requestToken },
				body: new URLSearchParams(new FormData(form)).toString(),
			})
			const result = await response.json()
			if (!response.ok) throw new Error(result.message || 'Unable to save settings.')
			message.textContent = 'Settings saved.'
			form.querySelector('#ngsign-api-token').value = ''
		} catch (error) {
			message.textContent = error.message || 'Unable to save settings.'
		} finally {
			submit.disabled = false
		}
	})
}
