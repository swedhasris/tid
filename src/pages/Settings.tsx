import React, { useState } from "react";
import { Button } from "@/components/ui/button";
import { useAuth } from "../contexts/AuthContext";
import { ShieldAlert, Zap } from "lucide-react";

export function Settings() {
  const { profile } = useAuth();
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState("");

  const triggerEscalation = async () => {
    setLoading(true);
    setMessage("");
    try {
      const response = await fetch("/api/tickets/trigger-escalation", {
        method: "POST",
      });
      const data = await response.json();
      setMessage(data.message);
    } catch (error) {
      setMessage("Failed to trigger escalation");
    } finally {
      setLoading(false);
    }
  };

  if (profile?.role !== "admin") {
    return (
      <div className="flex flex-col items-center justify-center h-[60vh] text-center space-y-4">
        <ShieldAlert className="w-16 h-16 text-muted-foreground opacity-20" />
        <h2 className="text-2xl font-bold">Access Restricted</h2>
        <p className="text-muted-foreground">Only administrators can access system settings.</p>
      </div>
    );
  }

  return (
    <div className="space-y-8">
      <div>
        <h1 className="text-3xl font-bold tracking-tight">System Settings</h1>
        <p className="text-muted-foreground">Configure global automation and system tools.</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
        <div className="sn-card">
          <div className="flex items-center gap-3 mb-4">
            <Zap className="w-6 h-6 text-sn-green" />
            <h3 className="text-lg font-bold">Workflow Automation</h3>
          </div>
          <p className="text-sm text-muted-foreground mb-6">
            The system automatically escalates tickets that haven't been updated in 24 hours. 
            This job runs hourly in the background.
          </p>
          
          <div className="space-y-4">
            <div className="flex items-center justify-between p-4 bg-muted rounded-lg">
              <div>
                <div className="font-medium">Escalation Job</div>
                <div className="text-xs text-muted-foreground">Status: Active (Hourly)</div>
              </div>
              <Button 
                onClick={triggerEscalation} 
                disabled={loading}
                variant="outline"
                size="sm"
              >
                {loading ? "Running..." : "Run Now"}
              </Button>
            </div>
            {message && (
              <p className="text-xs font-medium text-sn-green bg-sn-green/10 p-2 rounded text-center">
                {message}
              </p>
            )}
          </div>
        </div>

        <div className="sn-card opacity-50 pointer-events-none">
          <h3 className="text-lg font-bold mb-4">Email Notifications</h3>
          <p className="text-sm text-muted-foreground mb-6">
            Configure SMTP settings for automated email alerts.
          </p>
          <Button variant="outline" disabled>Configure</Button>
        </div>
      </div>
    </div>
  );
}
