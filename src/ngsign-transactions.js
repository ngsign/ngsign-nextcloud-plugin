const root = document.querySelector('#ngsign-transactions')
const escape = (value) => { const node = document.createElement('span'); node.textContent = value || ''; return node.innerHTML }

const statusLabel = (status) => status === 'SIGNED' ? 'Signed' : status === 'REFUSED' ? 'Refused' : status === 'CANCELLED' ? 'Cancelled' : status || 'Pending'
const statusClass = (status) => status === 'SIGNED' ? 'signed' : status === 'REFUSED' ? 'refused' : status === 'CANCELLED' ? 'cancelled' : 'pending'

const renderSigners = (item) => {
	const signers = item.signers || []
	if (!signers.length) return '<span class="ngsign-muted">—</span>'
	const list = signers.map((signer) => `<li class="ngsign-signer ngsign-signer-${statusClass(signer.status)}">${escape(signer.name)}</li>`).join('')
	const isOpen = item.status !== 'SIGNED' && item.status !== 'CANCELLED' && item.status !== 'REFUSED'
	const next = isOpen && item.nextSigner ? `<div class="ngsign-next-signer">Next: <strong>${escape(item.nextSigner)}</strong></div>` : ''
	return `<ul class="ngsign-signer-list">${list}</ul>${next}`
}

const renderActions = (item) => {
	const canDownload = item.status === 'SIGNED' && item.signedName
	const download = `<a class="button ${canDownload ? '' : 'disabled'}" ${canDownload ? `href="${OC.generateUrl('/apps/ngsign/transactions/' + encodeURIComponent(item.transactionId) + '/download')}"` : 'aria-disabled="true"'}>Download</a>`
	const check = `<button class="button ngsign-check" data-id="${escape(item.transactionId)}" ${item.status === 'SIGNED' ? 'disabled' : ''}>Check status</button>`
	const cancellable = item.status !== 'SIGNED' && item.status !== 'CANCELLED'
	const cancel = cancellable ? `<button class="button ngsign-cancel" data-id="${escape(item.transactionId)}">Cancel</button>` : ''
	return `${download} ${check} ${cancel}`
}

const PAGE_SIZE = 10
const state = { transactions: [], page: 1 }

const renderPagination = (pageCount) => {
	if (pageCount <= 1) return ''
	return `<div class="ngsign-pagination">
		<button type="button" class="button ngsign-page-prev" ${state.page <= 1 ? 'disabled' : ''}>Previous</button>
		<span class="ngsign-page-info">Page ${state.page} / ${pageCount}</span>
		<button type="button" class="button ngsign-page-next" ${state.page >= pageCount ? 'disabled' : ''}>Next</button>
	</div>`
}

const renderPage = () => {
	const { transactions } = state
	const signed = transactions.filter((item) => item.status === 'SIGNED').length
	const pending = transactions.length - signed
	const pageCount = Math.max(1, Math.ceil(transactions.length / PAGE_SIZE))
	state.page = Math.min(Math.max(1, state.page), pageCount)
	const pageItems = transactions.slice((state.page - 1) * PAGE_SIZE, state.page * PAGE_SIZE)

	root.innerHTML = `<h2>NGSign transactions</h2><div class="ngsign-transaction-stats"><div class="ngsign-stat"><strong>${transactions.length}</strong>Total transactions</div><div class="ngsign-stat"><strong>${pending}</strong>In progress</div><div class="ngsign-stat"><strong>${signed}</strong>Signed</div></div>${transactions.length ? `<table class="ngsign-transaction-table"><thead><tr><th>Document</th><th>Status</th><th>Signers</th><th>Created</th><th>Expiration</th><th>Actions</th></tr></thead><tbody>${pageItems.map((item) => `<tr><td>${escape(item.name)}</td><td><span class="ngsign-status-badge ngsign-status-${statusClass(item.status)}">${statusLabel(item.status)}</span></td><td>${renderSigners(item)}</td><td>${item.createdAt ? new Date(item.createdAt * 1000).toLocaleDateString() : '—'}</td><td>${item.expiresAt ? new Date(item.expiresAt * 1000).toLocaleDateString() : ''}</td><td>${renderActions(item)}</td></tr>`).join('')}</tbody></table>${renderPagination(pageCount)}` : '<p>No transactions launched from NGSign yet.</p>'}`

	root.querySelectorAll('.ngsign-check').forEach((button) => button.addEventListener('click', async () => {
		button.disabled = true; button.textContent = 'Checking…'
		try {
			const response = await fetch(OC.generateUrl('/apps/ngsign/transactions/' + encodeURIComponent(button.dataset.id) + '/check'), { method: 'POST', headers: { requesttoken: OC.requestToken } })
			const data = await response.json()
			if (!response.ok) throw new Error(data.message || 'Unable to check status.')
			await load()
		} catch (error) { button.textContent = error.message; button.disabled = false }
	}))

	root.querySelectorAll('.ngsign-cancel').forEach((button) => button.addEventListener('click', async () => {
		if (!window.confirm('Cancel this signature transaction?')) return
		button.disabled = true; button.textContent = 'Cancelling…'
		try {
			const response = await fetch(OC.generateUrl('/apps/ngsign/transactions/' + encodeURIComponent(button.dataset.id) + '/cancel'), { method: 'POST', headers: { requesttoken: OC.requestToken } })
			const data = await response.json()
			if (!response.ok) throw new Error(data.message || 'Unable to cancel the transaction.')
			await load()
		} catch (error) { button.textContent = error.message; button.disabled = false }
	}))

	const prev = root.querySelector('.ngsign-page-prev')
	if (prev) prev.addEventListener('click', () => { state.page -= 1; renderPage() })
	const next = root.querySelector('.ngsign-page-next')
	if (next) next.addEventListener('click', () => { state.page += 1; renderPage() })
}

const load = async () => {
	const response = await fetch(OC.generateUrl('/apps/ngsign/api/transactions'), { headers: { requesttoken: OC.requestToken } })
	const { transactions = [] } = await response.json()
	state.transactions = transactions
	renderPage()
}

load().catch(() => { root.innerHTML = '<h2>NGSign transactions</h2><p>Unable to load transactions.</p>' })
