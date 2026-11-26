export default function CommerceMoney({ amount, currency = "MAD", className = "" }) {
  if (!Number.isInteger(amount)) return <span className={className}>—</span>;
  return <span className={className}>{currency} {new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(amount / 100)}</span>;
}
