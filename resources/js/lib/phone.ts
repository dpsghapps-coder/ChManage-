/** Phone numbers are 10 digits starting with 0 (for example 0244123456); the server enforces the same rule. */
export const digitsOnly = (value: string) =>
    value.replace(/[^0-9]/g, '').slice(0, 10);

/** Props for a phone <input>. */
export const phoneInputProps = {
    type: 'tel',
    inputMode: 'numeric',
    maxLength: 10,
    pattern: '0[0-9]{9}',
    title: '10 digits, starting with 0',
    placeholder: '0244123456',
    autoComplete: 'off',
} as const;
