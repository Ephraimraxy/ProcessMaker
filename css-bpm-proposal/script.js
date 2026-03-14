document.addEventListener('DOMContentLoaded', () => {
    // Reveal animations on scroll
    const observerOptions = {
        threshold: 0.1
    };

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, observerOptions);

    document.querySelectorAll('.fade-in').forEach(el => observer.observe(el));

    // Scenarios Tab Logic
    const tabs = document.querySelectorAll('.tab');
    const content = document.getElementById('scenario-display');

    const scenarios = {
        procurement: {
            title: "Smart Procurement",
            description: "Go from purchase request to vendor payment with automated budget checks and multi-level approvals. Eliminate paperwork and capture every discount.",
            details: ["Automated budget validation", "Vendor performance tracking", "Multi-stage approval routing"]
        },
        hr: {
            title: "HR Transformation",
            description: "Automate onboarding checklists, leave management, and performance appraisal tracking to let your HR team focus on people, not paperwork.",
            details: ["Seamless onboarding", "SLA-tracked leave approvals", "Digital performance reviews"]
        },
        client: {
            title: "Client Service Desk",
            description: "Intake client issues with automated routing to the specific specialist needed. Never let a client request drop through the cracks.",
            details: ["Auto-routing by expertise", "Real-time client notifications", "SLA escalation"]
        },
        compliance: {
            title: "Compliance & EHS",
            description: "Standardized reporting for safety incidents with automated notification to authorities and management, ensuring 100% audit readiness.",
            details: ["Incident auto-reporting", "Compliance guardrails", "Digital audit trails"]
        }
    };

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            
            const type = tab.dataset.scenario;
            const data = scenarios[type];

            content.classList.remove('visible');
            setTimeout(() => {
                content.innerHTML = `
                    <h3 style="color: var(--accent); margin-bottom: 1rem;">${data.title}</h3>
                    <p style="font-size: 1.25rem; margin-bottom: 2rem;">${data.description}</p>
                    <ul style="list-style: none;">
                        ${data.details.map(d => `<li style="margin-bottom: 0.5rem; display: flex; align-items: center;"><span style="color: var(--success); margin-right: 10px;">✓</span> ${d}</li>`).join('')}
                    </ul>
                `;
                content.classList.add('visible');
            }, 300);
        });
    });
});
