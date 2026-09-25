import { useState } from 'react';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';

/**
 * A dropdown of listed values with "Other…" for anything else, which is then typed in. The typed or picked text is
 * the value; a saved value that is not on the list opens as "Other…" with its text.
 */
export function ChoiceOrOther({
    id,
    value,
    options,
    onChange,
    placeholder,
    required,
}: {
    id: string;
    value: string;
    options: string[];
    onChange: (value: string) => void;
    placeholder: string;
    required?: boolean;
}) {
    const [typing, setTyping] = useState(
        () => value !== '' && !options.includes(value),
    );
    const other = typing || (value !== '' && !options.includes(value));

    return (
        <>
            <NativeSelect
                id={id}
                value={other ? '__other' : value}
                onChange={(e) => {
                    const picked = e.target.value;
                    setTyping(picked === '__other');
                    onChange(picked === '__other' ? '' : picked);
                }}
                required={required && !other}
            >
                <option value="">Select…</option>
                {options.map((option) => (
                    <option key={option} value={option}>
                        {option}
                    </option>
                ))}
                <option value="__other">Other…</option>
            </NativeSelect>
            {other && (
                <Input
                    aria-label={`${placeholder}, in words`}
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    maxLength={150}
                    required={required}
                    autoFocus={typing}
                />
            )}
        </>
    );
}
