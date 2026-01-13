import { useNavigate } from 'react-router-dom';
import { Button } from '../../components/ui/button';
import { 
  Activity, 
  ShieldCheck, 
  Globe, 
  Zap,
  Users,
  FileText,
  Lock,
  Wifi,
  Clock,
  CheckCircle2,
  ArrowRight,
} from 'lucide-react';
import { Card } from '../../components/ui/card';

export default function WelcomePage() {
  const navigate = useNavigate();

  return (
    <div className="min-h-screen bg-white">
      {/* Header */}
      <header className="border-b border-slate-200 bg-white sticky top-0 z-50">
        <div className="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <Activity className="h-8 w-8 text-blue-600" />
            <span className="text-xl font-bold text-slate-900">OccHealth EHR</span>
          </div>
          <Button onClick={() => navigate('/login')} size="lg">
            Continue to Sign In
            <ArrowRight className="h-4 w-4 ml-2" />
          </Button>
        </div>
      </header>

      {/* Hero Section */}
      <section className="py-20 px-6 bg-gradient-to-br from-blue-50 to-slate-50">
        <div className="max-w-7xl mx-auto">
          <div className="max-w-3xl mx-auto text-center">
            <h1 className="text-5xl font-bold text-slate-900 mb-6">
              Occupational Health EHR
              <br />
              <span className="text-blue-600">Built for Speed & Compliance</span>
            </h1>
            <p className="text-xl text-slate-600 mb-8">
              Purpose-built for onsite clinics, industrial worksites, and occupational health providers. 
              Start encounters in seconds. Document with confidence. OSHA recordability detection powered by AI.
            </p>
            <div className="flex items-center justify-center gap-4">
              <Button size="lg" onClick={() => navigate('/login')}>
                Get Started
                <ArrowRight className="h-5 w-5 ml-2" />
              </Button>
              <Button size="lg" variant="outline">
                View Demo
              </Button>
            </div>
          </div>
        </div>
      </section>

      {/* Key Features */}
      <section className="py-20 px-6">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-12">
            <h2 className="text-3xl font-bold text-slate-900 mb-4">
              Everything You Need for Occupational Health
            </h2>
            <p className="text-lg text-slate-600 max-w-2xl mx-auto">
              Designed for clinical providers, nurses, technicians, and compliance officers working in high-volume, 
              time-sensitive environments.
            </p>
          </div>

          <div className="grid md:grid-cols-3 gap-8">
            <Card className="p-6">
              <Zap className="h-12 w-12 text-blue-600 mb-4" />
              <h3 className="text-xl font-semibold mb-3">Lightning Fast</h3>
              <p className="text-slate-600">
                Start encounters in 2 clicks. No unnecessary screens. Optimized workflows for 
                drug tests, fit tests, physicals, and injury management.
              </p>
            </Card>

            <Card className="p-6">
              <ShieldCheck className="h-12 w-12 text-green-600 mb-4" />
              <h3 className="text-xl font-semibold mb-3">Compliance First</h3>
              <p className="text-slate-600">
                HIPAA, OSHA, DOT ready. AI-powered OSHA recordability detection. 
                Full audit trails. Automated training tracking and access controls.
              </p>
            </Card>

            <Card className="p-6">
              <Wifi className="h-12 w-12 text-purple-600 mb-4" />
              <h3 className="text-xl font-semibold mb-3">Offline Capable</h3>
              <p className="text-slate-600">
                Work anywhere without internet. Auto-sync when connected. 
                Perfect for remote job sites and offshore installations.
              </p>
            </Card>

            <Card className="p-6">
              <Users className="h-12 w-12 text-orange-600 mb-4" />
              <h3 className="text-xl font-semibold mb-3">8 Role-Based Dashboards</h3>
              <p className="text-slate-600">
                Clinical Provider, Doctor, Technician, Registration, Manager, Super Manager, 
                Privacy Officer, and Security Officer roles—each with tailored workflows.
              </p>
            </Card>

            <Card className="p-6">
              <FileText className="h-12 w-12 text-indigo-600 mb-4" />
              <h3 className="text-xl font-semibold mb-3">Longitudinal Encounter Workspace</h3>
              <p className="text-slate-600">
                Non-linear documentation. Jump to any section. AI narrative generation. 
                EMS ePCR-style interface adapted for occupational health.
              </p>
            </Card>

            <Card className="p-6">
              <Lock className="h-12 w-12 text-red-600 mb-4" />
              <h3 className="text-xl font-semibold mb-3">Enterprise Security</h3>
              <p className="text-slate-600">
                SSO + 2FA authentication. Device trust management. Real-time audit logging. 
                Role-based access controls with automatic session management.
              </p>
            </Card>
          </div>
        </div>
      </section>

      {/* Workflow Highlights */}
      <section className="py-20 px-6 bg-slate-50">
        <div className="max-w-7xl mx-auto">
          <div className="grid md:grid-cols-2 gap-12 items-center">
            <div>
              <h2 className="text-3xl font-bold text-slate-900 mb-6">
                Optimized for Real Clinical Workflows
              </h2>
              <div className="space-y-4">
                <div className="flex items-start gap-3">
                  <CheckCircle2 className="h-6 w-6 text-green-600 mt-1" />
                  <div>
                    <h3 className="font-semibold mb-1">Giant Start Button</h3>
                    <p className="text-slate-600">
                      Every dashboard prioritizes starting encounters. No hunting through menus.
                    </p>
                  </div>
                </div>
                <div className="flex items-start gap-3">
                  <CheckCircle2 className="h-6 w-6 text-green-600 mt-1" />
                  <div>
                    <h3 className="font-semibold mb-1">Non-Linear Documentation</h3>
                    <p className="text-slate-600">
                      Jump between sections. Partial completion allowed. No wizard flows.
                    </p>
                  </div>
                </div>
                <div className="flex items-start gap-3">
                  <CheckCircle2 className="h-6 w-6 text-green-600 mt-1" />
                  <div>
                    <h3 className="font-semibold mb-1">AI-Powered Narrative</h3>
                    <p className="text-slate-600">
                      Generate clinical narratives instantly. OSHA recordability detection built-in.
                    </p>
                  </div>
                </div>
                <div className="flex items-start gap-3">
                  <CheckCircle2 className="h-6 w-6 text-green-600 mt-1" />
                  <div>
                    <h3 className="font-semibold mb-1">Continuous Auto-Save</h3>
                    <p className="text-slate-600">
                      Never lose work. Drafts saved continuously. Resume anytime.
                    </p>
                  </div>
                </div>
                <div className="flex items-start gap-3">
                  <CheckCircle2 className="h-6 w-6 text-green-600 mt-1" />
                  <div>
                    <h3 className="font-semibold mb-1">Validation Without Blocking</h3>
                    <p className="text-slate-600">
                      Advisory validation only. Never prevents navigation or saving.
                    </p>
                  </div>
                </div>
              </div>
            </div>
            <Card className="p-8 bg-gradient-to-br from-blue-600 to-blue-700 text-white">
              <Clock className="h-16 w-16 mb-6" />
              <h3 className="text-2xl font-bold mb-4">Time is Critical</h3>
              <p className="text-blue-100 mb-6">
                In occupational health, every second counts. Our system is designed to minimize 
                clicks, eliminate unnecessary steps, and get you to patient care faster.
              </p>
              <div className="space-y-3">
                <div className="flex items-center gap-3">
                  <div className="bg-blue-500 rounded-full p-2">
                    <Zap className="h-5 w-5" />
                  </div>
                  <span>Start encounters in under 5 seconds</span>
                </div>
                <div className="flex items-center gap-3">
                  <div className="bg-blue-500 rounded-full p-2">
                    <FileText className="h-5 w-5" />
                  </div>
                  <span>Generate narratives with one click</span>
                </div>
                <div className="flex items-center gap-3">
                  <div className="bg-blue-500 rounded-full p-2">
                    <CheckCircle2 className="h-5 w-5" />
                  </div>
                  <span>Submit encounters in 3 clicks</span>
                </div>
              </div>
            </Card>
          </div>
        </div>
      </section>

      {/* OSHA & Compliance Section */}
      <section className="py-20 px-6">
        <div className="max-w-7xl mx-auto">
          <div className="text-center mb-12">
            <h2 className="text-3xl font-bold text-slate-900 mb-4">
              OSHA Compliance Built-In
            </h2>
            <p className="text-lg text-slate-600 max-w-2xl mx-auto">
              AI-powered recordability detection, automated 300 log tracking, and compliance 
              workflows that protect your organization.
            </p>
          </div>

          <div className="grid md:grid-cols-2 gap-8">
            <Card className="p-6 border-amber-200 bg-amber-50">
              <h3 className="text-xl font-semibold mb-3 flex items-center gap-2">
                <ShieldCheck className="h-6 w-6 text-amber-600" />
                Automatic OSHA Recordability
              </h3>
              <p className="text-slate-700">
                Our AI scans narratives and treatment plans to flag potential OSHA recordable 
                incidents. Get recommendations in real-time as you document.
              </p>
            </Card>

            <Card className="p-6 border-green-200 bg-green-50">
              <h3 className="text-xl font-semibold mb-3 flex items-center gap-2">
                <Lock className="h-6 w-6 text-green-600" />
                Full Audit Trails
              </h3>
              <p className="text-slate-700">
                Every action logged with user, device, timestamp, and IP. Privacy and Security 
                Officer dashboards provide real-time monitoring and compliance reporting.
              </p>
            </Card>
          </div>
        </div>
      </section>

      {/* CTA Section */}
      <section className="py-20 px-6 bg-gradient-to-br from-blue-600 to-blue-700 text-white">
        <div className="max-w-4xl mx-auto text-center">
          <h2 className="text-4xl font-bold mb-6">
            Ready to Transform Your Occupational Health Practice?
          </h2>
          <p className="text-xl text-blue-100 mb-8">
            Join hundreds of onsite clinics and industrial health providers using OccHealth EHR 
            to deliver faster, more compliant care.
          </p>
          <Button size="lg" variant="secondary" onClick={() => navigate('/login')}>
            Sign In to Get Started
            <ArrowRight className="h-5 w-5 ml-2" />
          </Button>
        </div>
      </section>

      {/* Footer */}
      <footer className="bg-slate-900 text-slate-300 py-12 px-6">
        <div className="max-w-7xl mx-auto">
          <div className="grid md:grid-cols-4 gap-8 mb-8">
            <div>
              <div className="flex items-center gap-2 mb-4">
                <Activity className="h-6 w-6 text-blue-400" />
                <span className="font-semibold text-white">OccHealth EHR</span>
              </div>
              <p className="text-sm text-slate-400">
                Purpose-built for occupational health providers and onsite clinics.
              </p>
            </div>
            <div>
              <h4 className="font-semibold text-white mb-3">Product</h4>
              <ul className="space-y-2 text-sm">
                <li><a href="#" className="hover:text-white">Features</a></li>
                <li><a href="#" className="hover:text-white">Security</a></li>
                <li><a href="#" className="hover:text-white">Compliance</a></li>
                <li><a href="#" className="hover:text-white">Pricing</a></li>
              </ul>
            </div>
            <div>
              <h4 className="font-semibold text-white mb-3">Resources</h4>
              <ul className="space-y-2 text-sm">
                <li><a href="#" className="hover:text-white">Documentation</a></li>
                <li><a href="#" className="hover:text-white">Training</a></li>
                <li><a href="#" className="hover:text-white">Support</a></li>
                <li><a href="#" className="hover:text-white">API</a></li>
              </ul>
            </div>
            <div>
              <h4 className="font-semibold text-white mb-3">Company</h4>
              <ul className="space-y-2 text-sm">
                <li><a href="#" className="hover:text-white">About</a></li>
                <li><a href="#" className="hover:text-white">Contact</a></li>
                <li><a href="#" className="hover:text-white">Privacy</a></li>
                <li><a href="#" className="hover:text-white">Terms</a></li>
              </ul>
            </div>
          </div>
          <div className="border-t border-slate-800 pt-8">
            <div className="flex flex-col md:flex-row items-center justify-between gap-4">
              <p className="text-sm text-slate-400">
                © 2024 OccHealth EHR. All rights reserved.
              </p>
              <div className="flex items-center gap-4 text-sm">
                <span className="flex items-center gap-1">
                  <CheckCircle2 className="h-4 w-4 text-green-400" />
                  HIPAA Compliant
                </span>
                <span className="flex items-center gap-1">
                  <CheckCircle2 className="h-4 w-4 text-green-400" />
                  SOC 2 Type II
                </span>
                <span className="flex items-center gap-1">
                  <CheckCircle2 className="h-4 w-4 text-green-400" />
                  OSHA Ready
                </span>
              </div>
            </div>
          </div>
        </div>
      </footer>
    </div>
  );
}
