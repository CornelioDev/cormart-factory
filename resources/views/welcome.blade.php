<!DOCTYPE html>
<html class="light" lang="es">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Cormart Factory | Soluciones de Factoring Institucional</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "tertiary-fixed-dim": "#8dcdff",
                        "on-surface": "#1a1c1d",
                        "on-secondary-container": "#636467",
                        "surface-container-lowest": "#ffffff",
                        "surface-container": "#eeeeef",
                        "primary": "#895100",
                        "error-container": "#ffdad6",
                        "on-secondary-fixed-variant": "#454749",
                        "inverse-on-surface": "#f0f1f2",
                        "on-primary-fixed-variant": "#683d00",
                        "on-error": "#ffffff",
                        "surface-dim": "#d9dadb",
                        "outline": "#877462",
                        "on-error-container": "#93000a",
                        "on-tertiary-fixed-variant": "#004b70",
                        "primary-container": "#e38a00",
                        "surface-container-low": "#f3f3f4",
                        "on-primary-container": "#512e00",
                        "on-tertiary-fixed": "#001e30",
                        "tertiary-fixed": "#cae6ff",
                        "surface": "#f9f9fa",
                        "surface-container-high": "#e8e8e9",
                        "secondary": "#5d5e61",
                        "on-tertiary-container": "#003a57",
                        "inverse-surface": "#2f3132",
                        "error": "#ba1a1a",
                        "outline-variant": "#d9c2ae",
                        "primary-fixed": "#ffdcbc",
                        "on-secondary-fixed": "#1a1c1e",
                        "on-secondary": "#ffffff",
                        "surface-tint": "#895100",
                        "tertiary": "#006493",
                        "inverse-primary": "#ffb86b",
                        "on-background": "#1a1c1d",
                        "on-primary": "#ffffff",
                        "on-surface-variant": "#544434",
                        "surface-variant": "#e2e2e3",
                        "secondary-container": "#e2e2e5",
                        "secondary-fixed-dim": "#c6c6c9",
                        "on-tertiary": "#ffffff",
                        "primary-fixed-dim": "#ffb86b",
                        "on-primary-fixed": "#2c1700",
                        "surface-container-highest": "#e2e2e3",
                        "surface-bright": "#f9f9fa",
                        "tertiary-container": "#05a8f3",
                        "background": "#f9f9fa",
                        "secondary-fixed": "#e2e2e5"
                    },
                    fontFamily: {
                        "headline": ["Manrope"],
                        "body": ["Inter"],
                        "label": ["Inter"]
                    },
                    borderRadius: {"DEFAULT": "0.125rem", "lg": "0.25rem", "xl": "0.5rem", "full": "0.75rem"},
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
        .editorial-shadow {
            box-shadow: 0 8px 40px rgba(26, 28, 29, 0.06);
        }
        .signature-gradient {
            background: linear-gradient(135deg, #895100 0%, #e38a00 100%);
        }
        .pipeline-card-glow {
            box-shadow: 0 0 30px rgba(137, 81, 0, 0.15);
        }
    </style>
</head>
<body class="bg-surface font-body text-on-surface selection:bg-primary-fixed selection:text-on-primary-fixed">

    <!-- TopAppBar -->
    <header class="w-full top-0 sticky z-50 bg-white dark:bg-slate-900">
        <div class="flex justify-between items-center px-8 py-6 max-w-7xl mx-auto">
            <div class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white font-headline">
                Cormart Factory
            </div>
            <div class="flex items-center gap-6">
                @auth
                    <a href="/admin" class="bg-surface-container-highest text-on-surface px-6 py-2 rounded-md font-medium text-sm hover:bg-surface-variant transition-colors">
                        Ir al Panel
                    </a>
                @else
                    <a href="/admin" class="bg-surface-container-highest text-on-surface px-6 py-2 rounded-md font-medium text-sm hover:bg-surface-variant transition-colors">
                        Iniciar Sesión
                    </a>
                @endauth
            </div>
        </div>
    </header>

    <main>
        <!-- Hero Section -->
        <section class="relative pt-20 pb-32 overflow-hidden">
            <div class="max-w-7xl mx-auto px-8 grid grid-cols-1 lg:grid-cols-12 gap-12 items-center">
                <div class="lg:col-span-7">
                    <span class="inline-block py-1 px-3 bg-primary-container/10 text-primary font-semibold text-xs tracking-widest uppercase mb-6 rounded-sm">
                        Soluciones de Factoring para PyMEs
                    </span>
                    <h1 class="font-headline text-5xl md:text-7xl font-extrabold tracking-tight text-on-surface leading-[1.1] mb-8">
                        Liquidez Inmediata <br/>
                        <span class="text-primary-container">sin burocracia</span> <br/>
                        para PyMEs locales.
                    </h1>
                    <p class="text-secondary text-lg md:text-xl max-w-2xl mb-10 leading-relaxed">
                        Impulse el crecimiento de su negocio con nuestra facilidad de liquidez a un plazo de 30 días. Cerramos la brecha entre sus facturas y su potencial, apoyando a las pequeñas empresas con precisión institucional.
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <a class="signature-gradient text-on-primary px-8 py-4 rounded-md font-semibold text-base flex items-center gap-2 editorial-shadow hover:opacity-90 transition-opacity" href="#waitlist">
                            Empezar Ahora
                            <span class="material-symbols-outlined text-xl">arrow_forward</span>
                        </a>
                        <div class="flex items-center gap-3 px-6 py-4">
                            <span class="material-symbols-outlined text-primary-container">verified</span>
                            <span class="text-sm font-medium text-on-surface-variant">Respaldo Institucional</span>
                        </div>
                    </div>
                </div>
                <div class="lg:col-span-5 relative">
                    <div class="aspect-square rounded-xl overflow-hidden editorial-shadow bg-surface-container-low">
                        <img alt="Reunión profesional" class="w-full h-full object-cover" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBlXMMfChzlkC1XF3h5StsOx700rDvYM4G0WZh9G3-9Kj4uYD9_0Ba556dykKve8FS_N8I-_V42FDAJueEuafWfMmdq-bFSCrVVUWQmbFqN4xfpJUXo2ZBTWU5OfMs6kz69n5Ez2xMfxyHg4uL1iURS2rZYo-k0eCG-YKS0sQPYzaFBEVY8bxPyznJEdpZTj_Tc3E7LLlcUx0rLH0tzaMjVlu0ojSM1aRO_3IXmQWeVn9acMU93NalABKBYcG18vIA_Fxb_GKIYsg2J"/>
                    </div>
                    <!-- Floating Data Card -->
                    <div class="absolute -bottom-6 -left-6 bg-surface-container-lowest p-6 rounded-lg editorial-shadow max-w-[240px]">
                        <div class="flex items-center gap-4 mb-3">
                            <div class="w-10 h-10 rounded-full bg-primary-container/20 flex items-center justify-center">
                                <span class="material-symbols-outlined text-primary">speed</span>
                            </div>
                            <span class="font-bold text-2xl font-headline tracking-tight">24h</span>
                        </div>
                        <p class="text-xs text-secondary leading-snug">Tiempo promedio de aprobación para facturas PyME verificadas.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Investor Section -->
        <section class="bg-surface-container-low py-32">
            <div class="max-w-7xl mx-auto px-8">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-20 items-start">
                    <div>
                        <h2 class="font-headline text-4xl font-extrabold mb-8 tracking-tight">Oportunidades de Inversión Estratégica</h2>
                        <p class="text-secondary text-lg mb-12 leading-relaxed">
                            Cormart Factory opera como una cooperativa cerrada, priorizando el crecimiento del ecosistema empresarial local mientras entrega rendimientos estables y premium a nuestros socios de capital.
                        </p>
                        <div class="space-y-8">
                            <div class="flex gap-6">
                                <div class="shrink-0 w-12 h-12 rounded bg-surface-container-lowest flex items-center justify-center">
                                    <span class="material-symbols-outlined text-primary">trending_up</span>
                                </div>
                                <div>
                                    <h4 class="font-headline font-bold text-xl mb-2">1% Mensual Fijo</h4>
                                    <p class="text-on-surface-variant">Rendimiento mensual constante sobre el capital con protocolos de seguridad institucional.</p>
                                </div>
                            </div>
                            <div class="flex gap-6">
                                <div class="shrink-0 w-12 h-12 rounded bg-surface-container-lowest flex items-center justify-center">
                                    <span class="material-symbols-outlined text-primary">account_balance</span>
                                </div>
                                <div>
                                    <h4 class="font-headline font-bold text-xl mb-2">Distribución Variable Trimestral</h4>
                                    <p class="text-on-surface-variant">Distribución del capital excedente, pagada trimestralmente a todos los inversores.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-surface-container-lowest p-12 rounded-xl editorial-shadow">
                        <div class="border-l-4 border-primary-container pl-8 py-4 mb-8">
                            <h3 class="font-headline text-2xl font-bold mb-4">Nuestro Modelo Cooperativo</h3>
                            <p class="text-secondary leading-relaxed">Operamos bajo un modelo cooperativo dedicado al fortalecimiento del ecosistema empresarial local. Priorizamos el beneficio mutuo y el crecimiento sostenible de las PyMEs sobre el lucro individual, optimizando cada unidad de capital para reducir las barreras financieras mientras mantenemos los más altos estándares de seguridad para nuestros inversores.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mt-12">
                            <div class="p-4 bg-surface rounded">
                                <span class="block text-primary font-bold text-2xl mb-1 tracking-tighter">100%</span>
                                <span class="text-[10px] uppercase tracking-widest text-secondary font-semibold">Respaldado por Activos</span>
                            </div>
                            <div class="p-4 bg-surface rounded">
                                <span class="block text-primary font-bold text-2xl mb-1 tracking-tighter">Trimestral</span>
                                <span class="text-[10px] uppercase tracking-widest text-secondary font-semibold">Pagos</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Progress Tracker Visualization -->
        <section class="w-full bg-slate-900 py-32 text-white">
            <div class="max-w-7xl mx-auto px-8">
                <div class="text-center mb-20">
                    <h3 class="font-headline text-3xl font-bold mb-4">Pipeline de Liquidez Optimizado</h3>
                    <p class="text-slate-400 max-w-2xl mx-auto">Desde la presentación de la factura hasta el despliegue de capital en cuatro pasos sin interrupciones.</p>
                </div>
                <div class="relative py-12">
                    <!-- Desktop Horizontal Progress Line -->
                    <div class="hidden lg:block h-[2px] bg-slate-800 w-full absolute top-1/2 -translate-y-1/2"></div>
                    <div class="hidden lg:block h-[2px] bg-primary-container w-3/4 absolute top-1/2 -translate-y-1/2 transition-all duration-1000 shadow-[0_0_15px_#e38a00]"></div>
                    <!-- Mobile Vertical Progress Line -->
                    <div class="lg:hidden absolute left-[27px] top-0 bottom-0 w-[2px] bg-slate-800">
                        <div class="w-full h-3/4 bg-primary-container shadow-[0_0_15px_#e38a00]"></div>
                    </div>
                    <div class="relative flex flex-col lg:flex-row lg:justify-between gap-12 lg:gap-0 lg:items-center">
                        <!-- Step 1 -->
                        <div class="flex flex-row lg:flex-col items-center lg:items-center group gap-6 lg:gap-0">
                            <div class="w-14 h-14 shrink-0 rounded-2xl bg-primary-container text-on-primary flex items-center justify-center z-10 pipeline-card-glow transition-transform group-hover:scale-110">
                                <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' 1;">check</span>
                            </div>
                            <div class="lg:mt-6 text-left lg:text-center">
                                <span class="block font-label text-xs font-bold uppercase tracking-widest text-primary-container mb-1">ENVÍO</span>
                                <span class="text-[10px] text-slate-500 font-medium">SUBMISSION</span>
                            </div>
                        </div>
                        <!-- Step 2 -->
                        <div class="flex flex-row lg:flex-col items-center lg:items-center group gap-6 lg:gap-0">
                            <div class="w-14 h-14 shrink-0 rounded-2xl bg-primary-container text-on-primary flex items-center justify-center z-10 pipeline-card-glow transition-transform group-hover:scale-110">
                                <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' 1;">check</span>
                            </div>
                            <div class="lg:mt-6 text-left lg:text-center">
                                <span class="block font-label text-xs font-bold uppercase tracking-widest text-primary-container mb-1">VERIFICACIÓN</span>
                                <span class="text-[10px] text-slate-500 font-medium">VERIFICATION</span>
                            </div>
                        </div>
                        <!-- Step 3 -->
                        <div class="flex flex-row lg:flex-col items-center lg:items-center group gap-6 lg:gap-0">
                            <div class="w-14 h-14 shrink-0 rounded-2xl bg-primary-container text-on-primary flex items-center justify-center z-10 pipeline-card-glow transition-transform group-hover:scale-110">
                                <span class="material-symbols-outlined text-2xl" style="font-variation-settings: 'FILL' 1;">sync</span>
                            </div>
                            <div class="lg:mt-6 text-left lg:text-center">
                                <span class="block font-label text-xs font-bold uppercase tracking-widest text-primary-container mb-1">FINANCIAMIENTO</span>
                                <span class="text-[10px] text-slate-500 font-medium">FUNDING</span>
                            </div>
                        </div>
                        <!-- Step 4 -->
                        <div class="flex flex-row lg:flex-col items-center lg:items-center opacity-30 grayscale group gap-6 lg:gap-0">
                            <div class="w-14 h-14 shrink-0 rounded-2xl bg-slate-800 text-slate-400 flex items-center justify-center z-10 transition-transform group-hover:scale-110">
                                <span class="material-symbols-outlined text-2xl">flag</span>
                            </div>
                            <div class="lg:mt-6 text-left lg:text-center">
                                <span class="block font-label text-xs font-bold uppercase tracking-widest text-slate-400 mb-1">CRECIMIENTO</span>
                                <span class="text-[10px] text-slate-600 font-medium">GROWTH</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Waitlist Form Section -->
        <section class="max-w-7xl mx-auto px-8 py-32 grid grid-cols-1 lg:grid-cols-12 gap-16 items-center" id="waitlist">
            <div class="lg:col-span-5">
                <h2 class="font-headline text-4xl font-extrabold mb-6 tracking-tight">Únete a la Lista de Espera</h2>
                <p class="text-secondary text-lg mb-8 leading-relaxed">
                    Actualmente estamos incorporando a un grupo selecto de PyMEs locales e Inversores Institucionales. Reserve su acceso prioritario al próximo ciclo de liquidez.
                </p>
                <div class="space-y-4">
                    <div class="flex items-center gap-4 text-on-surface-variant">
                        <span class="material-symbols-outlined text-primary-container">support_agent</span>
                        <span class="text-sm">Gerente de cuenta dedicado para cada PyME.</span>
                    </div>
                    <div class="flex items-center gap-4 text-on-surface-variant">
                        <span class="material-symbols-outlined text-primary-container">encrypted</span>
                        <span class="text-sm">Cifrado de datos y privacidad de grado empresarial.</span>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-7 bg-white p-10 md:p-14 rounded-xl editorial-shadow">
                <form class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-xs font-bold uppercase tracking-widest text-secondary">Nombre</label>
                            <input class="w-full bg-surface-container-low border-none rounded-md px-4 py-3 focus:ring-0 focus:border-b-2 focus:border-primary-container transition-all" placeholder="Primer Nombre" type="text"/>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold uppercase tracking-widest text-secondary">Apellido</label>
                            <input class="w-full bg-surface-container-low border-none rounded-md px-4 py-3 focus:ring-0 focus:border-b-2 focus:border-primary-container transition-all" placeholder="Apellidos" type="text"/>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <label class="text-xs font-bold uppercase tracking-widest text-secondary">Razón Social</label>
                        <input class="w-full bg-surface-container-low border-none rounded-md px-4 py-3 focus:ring-0 focus:border-b-2 focus:border-primary-container transition-all" placeholder="Nombre oficial de la empresa" type="text"/>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-2">
                            <label class="text-xs font-bold uppercase tracking-widest text-secondary">RNC (Opcional)</label>
                            <input class="w-full bg-surface-container-low border-none rounded-md px-4 py-3 focus:ring-0 focus:border-b-2 focus:border-primary-container transition-all" placeholder="Identificación Tributaria" type="text"/>
                        </div>
                        <div class="space-y-2">
                            <label class="text-xs font-bold uppercase tracking-widest text-secondary">Número de Contacto</label>
                            <input class="w-full bg-surface-container-low border-none rounded-md px-4 py-3 focus:ring-0 focus:border-b-2 focus:border-primary-container transition-all" placeholder="+1 (000) 000-0000" type="tel"/>
                        </div>
                    </div>
                    <div class="pt-4">
                        <button class="w-full signature-gradient text-on-primary py-4 rounded-md font-bold text-lg editorial-shadow hover:opacity-95 transition-opacity" type="submit">
                            Registrar Interés
                        </button>
                        <p class="text-center text-[10px] text-on-secondary-container mt-6 px-8">
                            Al hacer clic en registrar, acepta nuestros Términos de Servicio y Política de Privacidad con respecto a comunicaciones institucionales.
                        </p>
                    </div>
                </form>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer class="w-full py-12 px-8 mt-20 bg-slate-100 dark:bg-slate-950">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-7xl mx-auto">
            <div class="space-y-6">
                <div class="text-lg font-bold text-slate-900 dark:text-white font-headline">Cormart Factory</div>
                <p class="text-sm text-slate-500 max-w-sm leading-relaxed">
                    Soluciones de Factoring Institucional diseñadas para la economía local moderna. Construyendo las bases para un crecimiento PyME sostenible.
                </p>
                <p class="text-[10px] font-medium tracking-wide uppercase text-slate-400">
                    &copy; {{ date('Y') }} Cormart Factory. Todos los derechos reservados. Soluciones de Factoring Institucional.
                </p>
            </div>
            <div class="grid grid-cols-2 gap-8 md:justify-items-end">
                <ul class="space-y-3">
                    <li><a class="text-slate-500 hover:text-amber-600 transition-colors text-xs font-medium tracking-wide uppercase" href="#">Términos de Servicio</a></li>
                    <li><a class="text-slate-500 hover:text-amber-600 transition-colors text-xs font-medium tracking-wide uppercase" href="#">Política de Privacidad</a></li>
                    <li><a class="text-slate-500 hover:text-amber-600 transition-colors text-xs font-medium tracking-wide uppercase" href="#">Contáctenos</a></li>
                </ul>
                <ul class="space-y-3">
                    <li><a class="text-slate-500 hover:text-amber-600 transition-colors text-xs font-medium tracking-wide uppercase" href="#">Relaciones con Inversores</a></li>
                    <li><a class="text-slate-500 hover:text-amber-600 transition-colors text-xs font-medium tracking-wide uppercase" href="#">Divulgación Regulatoria</a></li>
                </ul>
            </div>
        </div>
    </footer>

</body>
</html>
