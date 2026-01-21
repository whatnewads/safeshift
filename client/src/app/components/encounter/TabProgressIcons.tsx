import { useMemo } from 'react';
import {
  FileText,
  User,
  AlignLeft,
  FileCheck,
  PenTool,
  Stethoscope,
  type LucideIcon,
} from 'lucide-react';
import type { TabStatus } from '../../hooks/useRequiredFields.js';

/**
 * Tab icon configuration mapping tab IDs to their respective icons
 *
 * Current Tab Structure (6 tabs):
 * - incident: FileText
 * - patient: User
 * - objectiveFindings: Stethoscope (Combined tab for assessments, vitals, and treatments)
 * - narrative: AlignLeft
 * - disposition: FileCheck
 * - signatures: PenTool
 */
const TAB_ICONS: Record<string, LucideIcon> = {
  incident: FileText,
  patient: User,
  objectiveFindings: Stethoscope,  // Combined tab for assessments, vitals, and treatments
  narrative: AlignLeft,
  disposition: FileCheck,
  signatures: PenTool,
};

/**
 * Get the appropriate color class based on completion percentage
 * - Red: 0% completion (no fields filled)
 * - Yellow: 1-99% completion (partially filled)
 * - Green: 100% completion (all fields complete)
 */
const getStatusColor = (percentage: number, total: number): {
  iconColor: string;
  badgeColor: string;
  bgColor: string;
} => {
  // If no required fields, show as complete (green)
  if (total === 0) {
    return {
      iconColor: 'text-green-500 dark:text-green-400',
      badgeColor: 'bg-green-500 text-white',
      bgColor: 'bg-green-50 dark:bg-green-900/20',
    };
  }

  if (percentage === 100) {
    return {
      iconColor: 'text-green-500 dark:text-green-400',
      badgeColor: 'bg-green-500 text-white',
      bgColor: 'bg-green-50 dark:bg-green-900/20',
    };
  }

  if (percentage === 0) {
    return {
      iconColor: 'text-red-500 dark:text-red-400',
      badgeColor: 'bg-red-500 text-white',
      bgColor: 'bg-red-50 dark:bg-red-900/20',
    };
  }

  // 1-99% completion
  return {
    iconColor: 'text-amber-500 dark:text-amber-400',
    badgeColor: 'bg-amber-500 text-white',
    bgColor: 'bg-amber-50 dark:bg-amber-900/20',
  };
};

interface TabProgressIconProps {
  tabStatus: TabStatus;
  isActive?: boolean;
  onClick?: (tabId: string) => void;
}

/**
 * Individual tab progress icon with badge showing remaining fields
 */
function TabProgressIcon({ tabStatus, isActive, onClick }: TabProgressIconProps) {
  const Icon = TAB_ICONS[tabStatus.tabId];
  const remaining = tabStatus.total - tabStatus.completed;
  const colors = getStatusColor(tabStatus.percentage, tabStatus.total);

  if (!Icon) return null;

  const handleClick = () => {
    if (onClick) {
      onClick(tabStatus.tabId);
    }
  };

  return (
    <button
      type="button"
      onClick={handleClick}
      className={`
        relative flex items-center justify-center p-2 rounded-lg
        transition-all duration-200 hover:scale-110
        ${isActive ? 'ring-2 ring-blue-500 ring-offset-2 dark:ring-offset-slate-800' : ''}
        ${colors.bgColor}
      `}
      title={`${tabStatus.tabName}: ${tabStatus.completed}/${tabStatus.total} complete (${tabStatus.percentage}%)`}
    >
      <Icon className={`h-5 w-5 ${colors.iconColor}`} />
      
      {/* Badge showing remaining count */}
      {remaining > 0 && (
        <span
          className={`
            absolute -top-1 -right-1 min-w-[18px] h-[18px]
            flex items-center justify-center
            text-[10px] font-bold rounded-full
            ${colors.badgeColor}
          `}
        >
          {remaining > 9 ? '9+' : remaining}
        </span>
      )}
      
      {/* Checkmark for complete tabs */}
      {remaining === 0 && tabStatus.total > 0 && (
        <span
          className={`
            absolute -top-1 -right-1 w-[18px] h-[18px]
            flex items-center justify-center
            text-[10px] font-bold rounded-full
            ${colors.badgeColor}
          `}
        >
          ✓
        </span>
      )}
    </button>
  );
}

interface TabProgressIconsProps {
  allTabsStatus: TabStatus[];
  activeTab?: string;
  onTabClick?: (tabId: string) => void;
}

/**
 * TabProgressIcons Component
 * 
 * Displays a row of icons representing each EHR tab with color-coded status
 * and field count indicators. Place this below the percentage tracker in the side menu.
 * 
 * Color Coding:
 * - Red: 0% completion (no fields filled)
 * - Yellow: 1-99% completion (partially filled)  
 * - Green: 100% completion (all fields complete)
 * 
 * @param allTabsStatus - Array of TabStatus objects from useRequiredFields hook
 * @param activeTab - Currently active tab ID (optional, for highlighting)
 * @param onTabClick - Callback when a tab icon is clicked (optional)
 */
export function TabProgressIcons({
  allTabsStatus,
  activeTab,
  onTabClick,
}: TabProgressIconsProps) {
  // Filter to only tabs that have icons configured
  const tabsWithIcons = useMemo(() => {
    return allTabsStatus.filter((tab) => TAB_ICONS[tab.tabId]);
  }, [allTabsStatus]);

  // Calculate summary stats
  const summaryStats = useMemo(() => {
    const totalTabs = tabsWithIcons.length;
    const completeTabs = tabsWithIcons.filter((t) => t.isComplete).length;
    const incompleteTabs = totalTabs - completeTabs;
    return { totalTabs, completeTabs, incompleteTabs };
  }, [tabsWithIcons]);

  if (tabsWithIcons.length === 0) {
    return null;
  }

  return (
    <div className="space-y-2">
      {/* Icon grid */}
      <div className="flex flex-wrap gap-2 justify-center">
        {tabsWithIcons.map((tabStatus) => (
          <TabProgressIcon
            key={tabStatus.tabId}
            tabStatus={tabStatus}
            isActive={activeTab === tabStatus.tabId}
            onClick={onTabClick}
          />
        ))}
      </div>

      {/* Summary text */}
      <div className="text-center">
        <span className="text-xs text-slate-500 dark:text-slate-400">
          {summaryStats.completeTabs}/{summaryStats.totalTabs} tabs complete
        </span>
      </div>
    </div>
  );
}

/**
 * Hook helper to get per-tab completion data
 * This can be used if you need more granular control over the data
 */
export function useTabProgressData(allTabsStatus: TabStatus[]) {
  return useMemo(() => {
    return allTabsStatus.map((tab) => ({
      tabId: tab.tabId,
      tabName: tab.tabName,
      icon: TAB_ICONS[tab.tabId],
      completed: tab.completed,
      total: tab.total,
      remaining: tab.total - tab.completed,
      percentage: tab.percentage,
      isComplete: tab.isComplete,
      colors: getStatusColor(tab.percentage, tab.total),
    }));
  }, [allTabsStatus]);
}

export default TabProgressIcons;
