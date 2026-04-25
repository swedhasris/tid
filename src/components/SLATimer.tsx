import React, { useEffect, useState } from "react";
import { cn } from "@/lib/utils";

interface SLATimerProps {
  label: string;
  startTime: string;
  deadline: string;
  stoppedAt?: string;
  onHoldStart?: string | null;
  totalPausedTime?: number;
}

export function SLATimer({ 
  label, 
  startTime, 
  deadline, 
  stoppedAt, 
  onHoldStart, 
  totalPausedTime = 0 
}: SLATimerProps) {
  const [timeLeft, setTimeLeft] = useState<number>(0);
  const [percentage, setPercentage] = useState<number>(0);
  const [status, setStatus] = useState<"Within SLA" | "At Risk" | "Breached">("Within SLA");

  useEffect(() => {
    const timer = setInterval(() => {
      const start = new Date(startTime).getTime();
      const end = new Date(deadline).getTime();
      const totalDuration = end - start;
      const now = stoppedAt ? new Date(stoppedAt).getTime() : Date.now();
      
      let effectiveNow = now;
      let extraPaused = 0;
      
      if (onHoldStart && !stoppedAt) {
        // Currently on hold, don't count time passed since onHoldStart
        extraPaused = now - new Date(onHoldStart).getTime();
        effectiveNow = new Date(onHoldStart).getTime();
      }

      const elapsed = (effectiveNow - start) - totalPausedTime;
      const remaining = totalDuration - elapsed;
      
      setTimeLeft(remaining);
      
      const percentUsed = Math.min(Math.max((elapsed / totalDuration) * 100, 0), 100);
      setPercentage(percentUsed);

      if (remaining <= 0) {
        setStatus("Breached");
      } else if (percentUsed >= 80) {
        setStatus("At Risk");
      } else {
        setStatus("Within SLA");
      }
    }, 1000);

    return () => clearInterval(timer);
  }, [startTime, deadline, stoppedAt, onHoldStart, totalPausedTime]);

  const formatTime = (ms: number) => {
    const isNegative = ms < 0;
    const absMs = Math.abs(ms);
    const h = Math.floor(absMs / (1000 * 60 * 60));
    const m = Math.floor((absMs % (1000 * 60 * 60)) / (1000 * 60));
    const s = Math.floor((absMs % (1000 * 60)) / 1000);
    return `${isNegative ? "-" : ""}${h.toString().padStart(2, "0")}:${m.toString().padStart(2, "0")}:${s.toString().padStart(2, "0")}`;
  };

  const getStatusColor = () => {
    if (status === "Breached") return "bg-red-500";
    if (status === "At Risk") return "bg-yellow-500";
    return "bg-sn-green";
  };

  const getTextColor = () => {
    if (status === "Breached") return "text-red-500";
    if (status === "At Risk") return "text-yellow-600";
    return "text-sn-green";
  };

  return (
    <div className="space-y-2 p-3 bg-muted/20 rounded-lg border border-border">
      <div className="flex items-center justify-between">
        <span className="text-xs font-semibold text-muted-foreground uppercase">{label}</span>
        <span className={cn("text-xs font-bold px-2 py-0.5 rounded-full uppercase", 
          status === "Breached" ? "bg-red-100 text-red-700" : 
          status === "At Risk" ? "bg-yellow-100 text-yellow-700" : 
          "bg-green-100 text-green-700"
        )}>
          {status}
        </span>
      </div>
      
      <div className="flex items-baseline justify-between">
        <div className={cn("text-xl font-mono font-bold", getTextColor())}>
          {formatTime(timeLeft)}
        </div>
        <div className="text-[10px] text-muted-foreground">
          Deadline: {new Date(deadline).toLocaleString()}
        </div>
      </div>

      <div className="space-y-1">
        <div className="w-full h-2 bg-muted rounded-full overflow-hidden">
          <div 
            className={cn("h-full transition-all duration-500", getStatusColor())} 
            style={{ width: `${percentage}%` }}
          />
        </div>
        <div className="flex justify-between text-[10px] text-muted-foreground">
          <span>{percentage.toFixed(0)}% used</span>
          {onHoldStart && !stoppedAt && <span className="text-blue-500 font-bold animate-pulse">PAUSED</span>}
        </div>
      </div>
    </div>
  );
}
