#!/usr/bin/env bash
# 01-create-lxc.sh
# Create the zenretreats-portal LXC container on aivigil-central-01.
# Run on the Proxmox host as root.

set -euo pipefail

VMID=210
HOSTNAME=zenretreats-portal
TEMPLATE_STORAGE=local
TEMPLATE_NAME=ubuntu-24.04-standard_24.04-1_amd64.tar.zst
ROOTFS_STORAGE=local-lvm
ROOTFS_SIZE=40
CPU_CORES=4
RAM_MB=4096
SWAP_MB=2048
NET_BRIDGE=vmbr1
NET_IP="10.10.0.50/24"
NET_GW="10.10.0.1"

# Generate a strong random root password (one-time — we add SSH keys in phase 2)
ROOT_PW="$(openssl rand -base64 24 | tr -d '/+=' | cut -c1-22)"

echo "Creating LXC container ${VMID} (${HOSTNAME})..."

# Confirm not already created
if pct status "${VMID}" &>/dev/null; then
  echo "ERROR: VMID ${VMID} already exists. Choose a different VMID or remove the existing container." >&2
  exit 1
fi

# Confirm template exists
if [[ ! -f "/var/lib/vz/template/cache/${TEMPLATE_NAME}" ]]; then
  echo "Downloading Ubuntu 24.04 LXC template..."
  pveam update
  pveam download "${TEMPLATE_STORAGE}" "${TEMPLATE_NAME}"
fi

# Create
pct create "${VMID}" "${TEMPLATE_STORAGE}:vztmpl/${TEMPLATE_NAME}" \
  --hostname "${HOSTNAME}" \
  --cores "${CPU_CORES}" \
  --memory "${RAM_MB}" \
  --swap "${SWAP_MB}" \
  --rootfs "${ROOTFS_STORAGE}:${ROOTFS_SIZE}" \
  --net0 "name=eth0,bridge=${NET_BRIDGE},ip=${NET_IP},gw=${NET_GW},firewall=1" \
  --nameserver "1.1.1.1 1.0.0.1" \
  --features nesting=1,keyctl=1 \
  --unprivileged 1 \
  --onboot 1 \
  --startup order=3 \
  --password "${ROOT_PW}"

# Start
echo "Starting container..."
pct start "${VMID}"

# Wait for network
echo "Waiting for container to become reachable..."
for i in {1..30}; do
  if pct exec "${VMID}" -- sh -c 'ip -4 addr show eth0 | grep -q inet'; then
    break
  fi
  sleep 1
done

# Confirm
pct exec "${VMID}" -- bash -c "hostnamectl && ip -4 addr show eth0"

echo ""
echo "========================================================"
echo "Container ${VMID} (${HOSTNAME}) ready at ${NET_IP}"
echo "One-time root password (use ONCE then disable password auth in provisioner):"
echo ""
echo "    ${ROOT_PW}"
echo ""
echo "Next: enter with 'pct enter ${VMID}' and run deploy/scripts/02-provision.sh"
echo "========================================================"
