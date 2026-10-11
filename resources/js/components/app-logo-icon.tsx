import type { SVGAttributes } from 'react';

/**
 * Monogram: an arched doorway crowned with a four-point star.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
            <path d="M20 1.5 22 6l4.5 2-4.5 2-2 4.5-2-4.5-4.5-2L18 6z" />
            <path
                fillRule="evenodd"
                clipRule="evenodd"
                d="M7 38.5V24c0-7.18 5.82-13 13-13s13 5.82 13 13v14.5H7Zm4-3.5h18V24a9 9 0 0 0-18 0v11Zm6.5 0v-7.5a2.5 2.5 0 0 1 5 0V35h-5Z"
            />
        </svg>
    );
}
