import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export type Option = { value: string; label: string };

/**
 * Select bound to a string value. With a placeholder, an empty value is
 * offered as an explicit "none" choice.
 */
export function OptionSelect({
    id,
    value,
    options,
    onChange,
    placeholder,
    ariaLabel,
    className,
}: {
    id?: string;
    value: string;
    options: Option[];
    onChange: (value: string) => void;
    placeholder?: string;
    ariaLabel?: string;
    className?: string;
}) {
    return (
        <Select
            value={value || (placeholder ? 'none' : '')}
            onValueChange={(next) => onChange(next === 'none' ? '' : next)}
        >
            <SelectTrigger id={id} aria-label={ariaLabel} className={className}>
                <SelectValue placeholder={placeholder} />
            </SelectTrigger>
            <SelectContent>
                {placeholder && (
                    <SelectItem value="none">{placeholder}</SelectItem>
                )}
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}
