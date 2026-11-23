export * from './user';
export * from './bank-account';
export * from './package';
export * from './booking';
export * from './trip';
export * from './refund';
export * from './notification';
export * from './review';

// ==========================================
// ALIAS BACKWARD COMPAT
// ==========================================
import type { BookingEloquent, PaymentEloquent } from './booking';
import type { PackageEloquent } from './package';
import type { TripEloquent } from './trip';
import type { RefundEloquent } from './refund';

/** @deprecated Gunakan `BookingResource` (client/admin) atau `BookingEloquent` (mitra). */
export type Booking = BookingEloquent;

/** @deprecated Gunakan `PackageResource` atau `PackageEloquent`. */
export type Package = PackageEloquent;

/** @deprecated Gunakan `TripResource` atau `TripEloquent`. */
export type Trip = TripEloquent;

/** @deprecated Gunakan `RefundResource` atau `RefundEloquent`. */
export type Refund = RefundEloquent;

/** @deprecated Gunakan `PaymentResource` atau `PaymentEloquent`. */
export type Payment = PaymentEloquent;