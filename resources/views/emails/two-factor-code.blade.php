<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        http-equiv="X-UA-Compatible"
        content="IE=edge"
    >

    <title>{{ $title }} | WorkLink</title>
</head>

<body
    style="
        margin: 0;
        padding: 0;
        background-color: #f8fafc;
        font-family: Arial, Helvetica, sans-serif;
        color: #334155;
    "
>

    {{-- Preheader --}}
    <div
        style="
            display: none;
            max-height: 0;
            overflow: hidden;
            opacity: 0;
            color: transparent;
        "
    >
        Tu código de verificación de WorkLink es {{ $code }}.
    </div>

    <table
        width="100%"
        cellpadding="0"
        cellspacing="0"
        border="0"
        role="presentation"
        style="
            width: 100%;
            background-color: #f8fafc;
        "
    >
        <tr>
            <td
                align="center"
                style="
                    padding: 35px 15px;
                "
            >

                {{-- Contenedor principal --}}
                <table
                    width="100%"
                    cellpadding="0"
                    cellspacing="0"
                    border="0"
                    role="presentation"
                    style="
                        width: 100%;
                        max-width: 600px;
                    "
                >

                    {{-- Logo --}}
                    <tr>
                        <td
                            align="center"
                            style="
                                padding: 0 0 25px;
                            "
                        >
                            <a
                                href="{{ config('app.frontend_url') }}"
                                style="
                                    text-decoration: none;
                                    display: inline-block;
                                "
                            >
                                <img
                                    src="{{ rtrim(config('app.frontend_url'), '/') }}/logo.png"
                                    alt="WorkLink"
                                    width="190"
                                    style="
                                        display: block;
                                        width: 190px;
                                        max-width: 190px;
                                        height: auto;
                                        border: 0;
                                        outline: none;
                                        text-decoration: none;
                                    "
                                >
                            </a>
                        </td>
                    </tr>

                    {{-- Tarjeta --}}
                    <tr>
                        <td
                            style="
                                background-color: #ffffff;
                                border: 1px solid #e2e8f0;
                                border-radius: 18px;
                                padding: 42px 38px;
                            "
                        >

                            {{-- Saludo --}}
                            <p
                                style="
                                    margin: 0 0 10px;
                                    color: #64748b;
                                    font-size: 15px;
                                    line-height: 24px;
                                "
                            >
                                Hola, {{ $name }}
                            </p>

                            {{-- Título --}}
                            <h1
                                style="
                                    margin: 0 0 16px;
                                    color: #0f172a;
                                    font-size: 25px;
                                    line-height: 34px;
                                    font-weight: 700;
                                "
                            >
                                {{ $title }}
                            </h1>

                            {{-- Descripción --}}
                            <p
                                style="
                                    margin: 0 0 28px;
                                    color: #475569;
                                    font-size: 15px;
                                    line-height: 25px;
                                "
                            >
                                {{ $description }}
                            </p>

                            {{-- Caja del código --}}
                            <table
                                width="100%"
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                                role="presentation"
                                style="
                                    width: 100%;
                                    margin: 0 0 22px;
                                "
                            >
                                <tr>
                                    <td
                                        align="center"
                                        style="
                                            background-color: #f5f3ff;
                                            border: 1px solid #ddd6fe;
                                            border-radius: 16px;
                                            padding: 28px 20px;
                                        "
                                    >
                                        <p
                                            style="
                                                margin: 0 0 12px;
                                                color: #64748b;
                                                font-size: 12px;
                                                line-height: 18px;
                                                font-weight: 700;
                                                letter-spacing: 1.3px;
                                                text-transform: uppercase;
                                            "
                                        >
                                            Código de verificación
                                        </p>

                                        <p
                                            style="
                                                margin: 0;
                                                color: #7c3aed;
                                                font-size: 38px;
                                                line-height: 48px;
                                                font-weight: 700;
                                                letter-spacing: 8px;
                                            "
                                        >
                                            {{ $code }}
                                        </p>
                                    </td>
                                </tr>
                            </table>

                            {{-- Expiración --}}
                            <p
                                style="
                                    margin: 0 0 30px;
                                    text-align: center;
                                    color: #64748b;
                                    font-size: 13px;
                                    line-height: 21px;
                                "
                            >
                                Este código expirará en
                                <strong
                                    style="
                                        color: #334155;
                                    "
                                >
                                    {{ $expirationMinutes }} minutos
                                </strong>.
                            </p>

                            {{-- Separador --}}
                            <table
                                width="100%"
                                cellpadding="0"
                                cellspacing="0"
                                border="0"
                                role="presentation"
                                style="
                                    width: 100%;
                                    margin: 0 0 26px;
                                "
                            >
                                <tr>
                                    <td
                                        style="
                                            border-top: 1px solid #e2e8f0;
                                            height: 1px;
                                            font-size: 1px;
                                            line-height: 1px;
                                        "
                                    >
                                        &nbsp;
                                    </td>
                                </tr>
                            </table>

                            {{-- Seguridad --}}
                            <h2
                                style="
                                    margin: 0 0 10px;
                                    color: #0f172a;
                                    font-size: 16px;
                                    line-height: 24px;
                                    font-weight: 700;
                                "
                            >
                                Protege tu cuenta
                            </h2>

                            <p
                                style="
                                    margin: 0 0 12px;
                                    color: #64748b;
                                    font-size: 14px;
                                    line-height: 23px;
                                "
                            >
                                Este código es personal y de un solo uso.
                                <strong style="color: #334155;">
                                    Nunca lo compartas con otra persona.
                                </strong>
                            </p>

                            <p
                                style="
                                    margin: 0;
                                    color: #64748b;
                                    font-size: 14px;
                                    line-height: 23px;
                                "
                            >
                                Si tú no realizaste esta solicitud, puedes
                                ignorar este correo. No se realizará ningún
                                cambio mientras el código no sea verificado.
                            </p>

                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td
                            align="center"
                            style="
                                padding: 28px 20px 10px;
                            "
                        >
                            <p
                                style="
                                    margin: 0 0 5px;
                                    color: #7c3aed;
                                    font-size: 15px;
                                    line-height: 22px;
                                    font-weight: 700;
                                "
                            >
                                WorkLink
                            </p>

                            <p
                                style="
                                    margin: 0 0 12px;
                                    color: #64748b;
                                    font-size: 13px;
                                    line-height: 20px;
                                "
                            >
                                Conectando talento con oportunidades
                            </p>

                            <p
                                style="
                                    margin: 0 0 6px;
                                    color: #94a3b8;
                                    font-size: 12px;
                                    line-height: 18px;
                                "
                            >
                                © {{ date('Y') }} WorkLink.
                                Todos los derechos reservados.
                            </p>

                            <p
                                style="
                                    margin: 0;
                                    color: #94a3b8;
                                    font-size: 11px;
                                    line-height: 17px;
                                "
                            >
                                Este es un mensaje automático.
                                Por favor, no respondas a este correo.
                            </p>
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>