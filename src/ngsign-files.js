import { FileType, registerFileAction } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'

const text = (value) => {
	const span = document.createElement('span')
	span.textContent = value
	return span.innerHTML
}

const showDebug = (container, debug) => {
	if (!Array.isArray(debug) || debug.length === 0) return
	const details = document.createElement('details')
	details.className = 'ngsign-debug'
	const summary = document.createElement('summary')
	summary.textContent = 'NGSign DEBUG (token and PDF content are masked)'
	const output = document.createElement('pre')
	output.textContent = JSON.stringify(debug, null, 2)
	details.append(summary, output)
	container.appendChild(details)
}

const signerRow = () => `<div class="ngsign-signer-row">
	<button type="button" class="ngsign-remove-signer" aria-label="${text(t('ngsign', 'Remove signer'))}">×</button>
	<input class="ngsign-user-search" list="ngsign-users" placeholder="Nextcloud user (optional)">
	<input name="firstName" required placeholder="${text(t('ngsign', 'First name'))}">
	<input name="lastName" required placeholder="${text(t('ngsign', 'Last name'))}">
	<input name="email" type="email" required placeholder="${text(t('ngsign', 'Email address'))}">
	<input name="phoneNumber" type="tel" placeholder="${text(t('ngsign', 'Phone (optional)'))}">
</div>`

const relativePath = (node) => {
	if (node.path) return node.path.replace(/^\//, '')
	const path = new URL(node.source, window.location.origin).pathname
	const marker = '/files/'
	const index = path.indexOf(marker)
	return decodeURIComponent(index === -1 ? path.replace(/^\//, '') : path.slice(path.indexOf('/', index + marker.length) + 1))
}

const openDialog = (node) => {
	const fileName = node.basename || node.filename || 'PDF'
	const overlay = document.createElement('div')
	overlay.className = 'ngsign-modal-overlay'
	overlay.innerHTML = `<section class="ngsign-modal" role="dialog" aria-modal="true" aria-labelledby="ngsign-title">
		<button type="button" class="ngsign-close" aria-label="${text(t('ngsign', 'Close'))}">×</button>
		<h2 id="ngsign-title">${text(t('ngsign', 'Sign with NGSign'))}</h2>
		<p class="ngsign-intro">${text(t('ngsign', 'Choose the recipients who will receive an email invitation to sign “{fileName}”.', { fileName }))}</p>
	<form><datalist id="ngsign-users"></datalist><div class="ngsign-signers">${signerRow()}</div><p><button type="button" class="ngsign-add-signer">${text(t('ngsign', 'Add signer'))}</button></p><p class="ngsign-status" aria-live="polite"></p><footer><button type="button" class="ngsign-cancel">${text(t('ngsign', 'Cancel'))}</button><button class="primary" type="submit">${text(t('ngsign', 'Launch signature'))}</button></footer></form>
	</section>`
	document.body.appendChild(overlay)
	const close = () => overlay.remove()
	overlay.querySelectorAll('.ngsign-close,.ngsign-cancel').forEach((button) => button.addEventListener('click', close))
	overlay.querySelector('.ngsign-add-signer').addEventListener('click', () => overlay.querySelector('.ngsign-signers').insertAdjacentHTML('beforeend', signerRow()))
	const loadUsers = async (input) => {
		if (!input.classList.contains('ngsign-user-search')) return
		const response = await fetch(OC.generateUrl('/apps/ngsign/users') + '?search=' + encodeURIComponent(input.value), { headers: { requesttoken: OC.requestToken } })
		if (!response.ok) return
		const data = await response.json()
		const list = overlay.querySelector('#ngsign-users')
		list.innerHTML = (data.users || []).map((user) => `<option value="${text(user.displayName)}" data-email="${text(user.email || '')}">`).join('')
	}
	overlay.addEventListener('focusin', (event) => { loadUsers(event.target) })
	overlay.addEventListener('input', (event) => {
		const input = event.target
		loadUsers(input)
	})
	overlay.addEventListener('change', (event) => {
		const input = event.target
		if (!input.classList.contains('ngsign-user-search')) return
		const option = [...overlay.querySelector('#ngsign-users').options].find((item) => item.value === input.value)
		if (!option) return
		const row = input.closest('.ngsign-signer-row'); const names = input.value.split(' ')
		row.querySelector('[name="firstName"]').value = names.shift() || ''
		row.querySelector('[name="lastName"]').value = names.join(' ')
		row.querySelector('[name="email"]').value = option.dataset.email || ''
	})
	overlay.addEventListener('click', (event) => {
		if (event.target === overlay) close()
		const remove = event.target.closest('.ngsign-remove-signer')
		if (remove && overlay.querySelectorAll('.ngsign-signer-row').length > 1) remove.closest('.ngsign-signer-row').remove()
	})
	overlay.querySelector('form').addEventListener('submit', async (event) => {
		event.preventDefault()
		const form = event.currentTarget
		if (!form.reportValidity()) return
		const status = overlay.querySelector('.ngsign-status')
		const submit = form.querySelector('[type="submit"]')
		const signers = [...overlay.querySelectorAll('.ngsign-signer-row')].map((row) => Object.fromEntries([...row.querySelectorAll('input')].map((input) => [input.name, input.value.trim()])))
		submit.disabled = true
		submit.dataset.label = submit.textContent
		submit.textContent = t('ngsign', 'Sending…')
		status.classList.remove('ngsign-status-error')
		status.textContent = t('ngsign', 'Sending document to NGSign…')
		try {
			const response = await fetch(OC.generateUrl('/apps/ngsign/signature/launch'), { method: 'POST', headers: { 'Content-Type': 'application/json', requesttoken: OC.requestToken }, body: JSON.stringify({ path: relativePath(node), signers }) })
			const result = await response.json()
			if (!response.ok) {
				const error = new Error(result.message || t('ngsign', 'NGSign could not start the signature.'))
				error.debug = result.debug
				throw error
			}
			if (result.signingUrl) {
				window.location.assign(result.signingUrl)
				return
			}
			const modal = overlay.querySelector('.ngsign-modal')
			modal.classList.add('ngsign-modal-success')
			modal.innerHTML = `<button type="button" class="ngsign-close" aria-label="${text(t('ngsign', 'Close'))}">×</button>
				<div class="ngsign-success-icon" aria-hidden="true">✓</div>
				<h2 id="ngsign-title">${text(t('ngsign', 'Signature request sent'))}</h2>
				<p>${text(t('ngsign', 'Your document has been sent to NGSign. Signers will receive an email invitation to sign it.'))}</p>
				<footer><button type="button" class="primary ngsign-done">${text(t('ngsign', 'Close'))}</button></footer>`
			showDebug(modal, result.debug)
			modal.querySelectorAll('.ngsign-close,.ngsign-done').forEach((button) => button.addEventListener('click', close))
		} catch (error) {
			status.classList.add('ngsign-status-error')
			status.textContent = error.message
			showDebug(status, error.debug)
			submit.disabled = false
			submit.textContent = submit.dataset.label
		}
	})
	overlay.querySelector('input').focus()
}

registerFileAction({
	id: 'ngsign-sign',
	order: 30,
	displayName: () => t('ngsign', 'Sign with NGSign'),
	iconSvgInline: () => '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25M20.71 7.04a1 1 0 0 0 0-1.41l-2.34-2.34a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83Z"/></svg>',
	enabled: ({ nodes }) => {
		const node = nodes.length === 1 ? nodes[0] : null
		return Boolean(node
			&& node.type === FileType.File
			&& (node.mime === 'application/pdf' || node.basename?.toLowerCase().endsWith('.pdf')))
	},
	exec: async ({ nodes }) => {
		openDialog(nodes[0])
		return null
	},
})
