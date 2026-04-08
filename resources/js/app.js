import './bootstrap'
import './map'
import '../css/filament/admin/theme.css'

// Global button progress feedback for page actions (excluding sidebar controls).
(function initButtonProgressFeedback() {
	if (window.__rideconnectButtonProgressInitialized) {
		return
	}
	window.__rideconnectButtonProgressInitialized = true

	const busyButtons = new Set()
	const fallbackTimers = new Map()
	let pendingRequests = 0

	const style = document.createElement('style')
	style.textContent = `
		.rc-btn-progress {
			position: relative;
			pointer-events: none;
			opacity: 0.92;
		}

		.rc-btn-progress::after {
			content: '';
			width: 0.9rem;
			height: 0.9rem;
			border-radius: 9999px;
			border: 2px solid currentColor;
			border-top-color: transparent;
			position: absolute;
			right: 0.55rem;
			top: 50%;
			transform: translateY(-50%);
			animation: rc-btn-spin 0.7s linear infinite;
			opacity: 0.85;
		}

		@keyframes rc-btn-spin {
			to {
				transform: translateY(-50%) rotate(360deg);
			}
		}
	`
	document.head.appendChild(style)

	const isSidebarButton = (element) => {
		return Boolean(element.closest([
			'.fi-sidebar',
			'.fi-sidebar-nav',
			'.fi-sidebar-item',
			'.fi-topbar-open-sidebar-btn',
			'[data-sidebar]',
			'aside[aria-label*="Sidebar"]',
		].join(',')))
	}

	const markBusy = (button) => {
		if (!button || busyButtons.has(button)) {
			return
		}

		busyButtons.add(button)
		button.classList.add('rc-btn-progress')
		button.setAttribute('aria-busy', 'true')

		const timer = window.setTimeout(() => {
			unmarkBusy(button)
		}, 10000)
		fallbackTimers.set(button, timer)
	}

	const unmarkBusy = (button) => {
		if (!button || !busyButtons.has(button)) {
			return
		}

		busyButtons.delete(button)
		button.classList.remove('rc-btn-progress')
		button.removeAttribute('aria-busy')

		const timer = fallbackTimers.get(button)
		if (timer) {
			window.clearTimeout(timer)
			fallbackTimers.delete(button)
		}
	}

	const clearBusyButtons = () => {
		busyButtons.forEach((button) => unmarkBusy(button))
	}

	const updatePendingRequests = (delta) => {
		pendingRequests = Math.max(0, pendingRequests + delta)
		if (pendingRequests === 0) {
			clearBusyButtons()
		}
	}

	const originalFetch = window.fetch
	if (typeof originalFetch === 'function') {
		window.fetch = async (...args) => {
			updatePendingRequests(1)
			try {
				return await originalFetch(...args)
			} finally {
				updatePendingRequests(-1)
			}
		}
	}

	const originalXhrOpen = XMLHttpRequest.prototype.open
	const originalXhrSend = XMLHttpRequest.prototype.send
	XMLHttpRequest.prototype.open = function (...args) {
		this.__rcTrack = true
		return originalXhrOpen.apply(this, args)
	}
	XMLHttpRequest.prototype.send = function (...args) {
		if (this.__rcTrack) {
			updatePendingRequests(1)
			this.addEventListener('loadend', () => updatePendingRequests(-1), { once: true })
		}
		return originalXhrSend.apply(this, args)
	}

	document.addEventListener('click', (event) => {
		const button = event.target?.closest?.('button, [role="button"], .fi-btn')
		if (!button) {
			return
		}

		if (button.disabled || button.getAttribute('aria-disabled') === 'true') {
			return
		}

		if (button.dataset.noProgress === 'true' || isSidebarButton(button)) {
			return
		}

		markBusy(button)

		if (pendingRequests === 0) {
			window.setTimeout(() => {
				if (pendingRequests === 0) {
					unmarkBusy(button)
				}
			}, 1200)
		}
	}, true)

	window.addEventListener('beforeunload', clearBusyButtons)
	document.addEventListener('livewire:navigated', clearBusyButtons)
})()
