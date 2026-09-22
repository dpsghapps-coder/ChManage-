/** Ghana country code, used to turn a local number (0244123456) into an international one (233244123456). */
const COUNTRY_CODE = '233';

/** A local number with anything but digits removed. */
const digits = (phone: string) => phone.replace(/[^0-9]/g, '');

/** Opens the phone's dialler. */
export const callUrl = (phone: string) => `tel:${digits(phone)}`;

/** WhatsApp only works on mobile lines: 02x and 05x. Landlines (03x) have no WhatsApp. */
export const canWhatsApp = (phone: string) =>
    /^0[25][0-9]{8}$/.test(digits(phone));

/** wa.me wants the number in international form without the leading 0 or a plus. */
export const whatsappUrl = (phone: string) =>
    `https://wa.me/${COUNTRY_CODE}${digits(phone).replace(/^0/, '')}`;
