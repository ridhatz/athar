import type { SVGAttributes } from 'react';

/**
 * The Athar mark: the Arabic word أثر ("trace / lasting effect") set as text so it follows the
 * surrounding colour in light and dark mode. Falls back through common Arabic system fonts.
 */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 48 40"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="Athar"
        >
            <text
                x="24"
                y="29"
                textAnchor="middle"
                lang="ar"
                direction="rtl"
                fontSize="30"
                fontWeight="700"
                fontFamily="'Noto Naskh Arabic','Amiri','Traditional Arabic','Segoe UI',serif"
            >
                أثر
            </text>
        </svg>
    );
}
