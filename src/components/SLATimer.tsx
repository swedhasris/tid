import React, { useEffect, useState } from "react";
import { cn } from "@/lib/utils";

interface SLATimerProps {
  label: string;
  deadline: string;
  startTime?: string;
  stoppedAt?: string;
  metAt?: string;
  isPaused?: boolean;
  onHoldStart?: string | null;
  totalPausedTime?: number;
  /** Resolution timer: pass firstResponseAt — timer won't start until this is set */
  waitUntil?: string | null;
}

export function SLATimer({
  label,
  deadline,
  startTime,
  stoppedAt,
  metAt,
  isPaused,
  onHoldStart,
  totalPausedTime = 0,
  waitUntil,
}: SLATimerProps) {
  const [timeLeft, setTimeLeft]     = useState<number>(0);
  const [percentage, setPercentage] = useState<number>(0);
  const [status, setStatus]         = useState<"waiting" | "met" | "Within SLA" | "At Risk" | "Breached">("Within SLA");

  useEffect(() => {
    // Already completed
    if (metAt || stoppedAt) {
      setStatus("met");
      setTimeLeft(0);
      setPercentage(100);
      return;
    }

    // Waiting for response before resolution can start
    // Only applies when waitUntil is explicitly passed (Resolution timer)
    // undefined means "not applicable" (Response timer) — proceed normally
    if (waitUntil !== undefined && (waitUntil === null || waitUntil === "")) {
      setStatus("waiting");
      setTimeLeft(0);
      setPercentage(0);
      return;
    }

    const deadlineMs = new Date(deadline).getTime();
    if (isNaN(deadlineMs)) return;

    // Resolution starts from when response was given
    const startMs = new Date(waitUntil).getTime();
    const totalDuration = deadlineMs - startMs;

    const tick = () => {
      const now = Date.now();
      const effectiveNow = (isPaused && onHoldStart) ? new Date(onHoldStart).getTime() : now;
      const remaining = deadlineMs - effectiveNow + (totalPausedTime || 0);
      const elapsed   = effectiveNow - startMs - (totalPausedTime || 0);

      setTimeLeft(remaining);

      const pct = Math.min(Math.max((elapsed / totalDuration) * 100, 0), 100);
      setPercentage(pct);

      if (remaining <= 0)    setStatus("Breached");
      else if (pct >= 80)    setStatus("At Risk");
      else                   setStatus("Within SLA");
    };

    tick();
    const timer = setInterval(tick, 1000);
    return () => clearInterval(timer);
  }, [deadline, startTime, stoppedAt, metAt, isPaused, onHoldStart, totalPausedTime, waitUntil]);

  const formatTime = (ms: number) => {
    const neg = ms < 0;
    const abs = Math.abs(ms);
    const h   = Math.floor(abs / 3600000);
    const m   = Math.floor((abs % 3600000) / 60000);
    const s   = Math.floor((abs % 60000) / 1000);
    return `${neg ? "-" : ""}${String(h).padStart(2,"0")}:${String(m).padStart(2,"0")}:${String(s).padStart(2,"0")}`;
  };

  const barColor  = status === "Breached" ? "bg-red-500"
                  : status === "At Risk"   ? "bg-yellow-500"
                  : status === "met"       ? "bg-blue-500"
                  : status === "waiting"   ? "bg-gray-300"
                  : "bg-sn-green";

  const textColor = status === "Breached" ? "text-red-500"
                  : status === "At Risk"   ? "text-yellow-600"
                  : status === "met"       ? "text-blue-600"
                  : status === "waiting"   ? "text-muted-foreground"
                  : "text-sn-green";

  const badgeCls  = status === "Breached" ? "bg-red-100 text-red-700"
                  : status === "At Risk"   ? "bg-yellow-100 text-yellow-700"
                  : status === "met"       ? "bg-blue-100 text-blue-700"
                  : status === "waiting"   ? "bg-gray-100 text-gray-500"
                  : "bg-green-100 text-green-700";

  const badgeLabel = status === "met"     ? "MET"
                   : status === "waiting" ? "PENDING"
                   : status;

  return (
    <div className="space-y-2 p-3 bg-muted/20 rounded-lg border border-border">
      <div className="flex items-center justify-between">
        <span className="text-xs font-semibold text-muted-foreground uppercase">{label}</span>
        <span className={cn("text-xs font-bold px-2 py-0.5 rounded-full uppercase", badgeCls)}>
          {badgeLabel}
        </span>
      </div>

      <div className="flex items-baseline justify-between">
        <div className={cn("text-xl font-mono font-bold", textColor)}>
          {status === "met"     ? "MET ✓"
         : status === "waiting" ? "Awaiting response..."
         : formatTime(timeLeft)}
        </div>
        <div className="text-[10px] text-muted-foreground">
          {status === "waiting"
            ? "Starts after response"
            : new Date(deadline).toLocaleString()}
        </div>
      </div>

      <div className="space-y-1">
        <div className="w-full h-2 bg-muted rounded-full overflow-hidden">
          <div className={cn("h-full transition-all duration-500", barColor)} style={{ width: `${percentage}%` }} />
        </div>
        <div className="flex justify-between text-[10px] text-muted-foreground">
          <span>{status === "waiting" ? "Not started" : `${percentage.toFixed(0)}% elapsed`}</span>
          {isPaused && !stoppedAt && !metAt && status !== "waiting" && (
            <span className="text-orange-500 font-bold animate-pulse">⏸ PAUSED</span>
          )}
        </div>
      </div>
    </div>
  );
}
