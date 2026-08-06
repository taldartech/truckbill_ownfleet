/**
 * Build a single-line customer address including city and state when available.
 *
 * @param {{ address?: string|null, city_name?: string|null, state_name?: string|null, city?: { name?: string|null }|null, state?: { name?: string|null }|null }|null|undefined} customer
 * @returns {string|null}
 */
export function formatCustomerAddress(customer) {
    if (!customer) {
        return null;
    }

    const parts = [
        customer.address,
        customer.city_name || customer.city?.name,
        customer.state_name || customer.state?.name,
    ]
        .map((part) => String(part ?? '').trim())
        .filter(Boolean);

    return parts.length > 0 ? parts.join(', ') : null;
}
